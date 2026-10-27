/**
 * The compose page (messages/create.blade.php): recipient search, the
 * pre-send checks, and everything that keeps a letter from being lost —
 * background saves to the server, a localStorage fallback for when the
 * server can't be reached, and a CSRF token that never goes stale.
 *
 * The Blade view passes in the letter as the server knows it, the URLs
 * it needs and the translated strings it shows.
 */
export default function compose({ letter, hasOldInput, storageKeyPrefix, urls, messages }) {
    return {
        query: letter.recipientName,
        recipientId: letter.recipientId,
        lastSelectedQuery: letter.recipientName,
        results: [],
        open: false,
        body: letter.body,
        enclosureUrls: letter.enclosures,
        fullscreen: false,
        submitting: false,
        recipientProblem: '',
        bodyProblem: '',
        storageKey: storageKeyPrefix + (letter.id ?? 'new'),
        serverCopy: null,
        restoredLocally: false,
        localTimer: null,
        draftId: letter.id,
        formAction: urls.form,
        autosaveUrl: urls.autosave,
        lastSavedJson: null,
        lastChangeAt: 0,
        lastAutosaveAt: Date.now(),
        autosaving: false,
        pendingSubmit: false,
        saveState: '',
        savedAt: null,

        init() {
            this.$watch('fullscreen', (value) => {
                document.body.classList.toggle('pp-fullscreen-editor', value);
            });

            this.serverCopy = this.snapshot();
            // Old input (e.g. after a 419) may never have reached the database.
            if (this.draftId && !hasOldInput) this.lastSavedJson = JSON.stringify(this.payload());
            this.restoreLocal();

            ['body', 'query', 'recipientId', 'enclosureUrls'].forEach((field) => {
                this.$watch(field, () => {
                    this.lastChangeAt = Date.now();
                    clearTimeout(this.localTimer);
                    this.localTimer = setTimeout(() => this.saveLocal(), 500);
                });
            });
            this.$watch('recipientId', (id) => { if (id) this.recipientProblem = ''; });
            this.$watch('body', (body) => { if (body.trim()) this.bodyProblem = ''; });

            // Save to the server once typing pauses for 2s, and at least every 20s
            // while it doesn't. After a failure, back off for 15s.
            setInterval(() => {
                if (!this.hasUnsavedChanges()) return;
                const sinceSave = Date.now() - this.lastAutosaveAt;
                if (this.saveState === 'failed' && sinceSave < 15000) return;
                if (Date.now() - this.lastChangeAt >= 2000 || sinceSave >= 20000) this.autosave();
            }, 2000);

            // Phones kill background tabs without warning, so save on the way out.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') this.autosave(true);
            });

            window.addEventListener('beforeunload', (event) => {
                if (this.hasUnsavedChanges() && !this.submitting) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            // Keeps the session (and its CSRF token) alive while the letter is open.
            setInterval(() => this.refreshCsrfToken(), 10 * 60 * 1000);
        },

        snapshot() {
            return {
                body: this.body,
                query: this.query,
                recipientId: this.recipientId,
                enclosureUrls: [...this.enclosureUrls],
            };
        },

        payload() {
            return {
                body: this.body,
                recipient_id: this.recipientId || null,
                recipient_name: this.query,
                enclosures: [...this.enclosureUrls],
            };
        },

        hasUnsavedChanges() {
            // A new letter with nothing written isn't worth keeping.
            if (!this.draftId && !this.body.trim()) return false;
            return JSON.stringify(this.payload()) !== this.lastSavedJson;
        },

        // --- Saving to the server -------------------------------------------------

        autosave(leavingPage = false) {
            if (this.autosaving || this.submitting || !this.hasUnsavedChanges()) return;

            const json = JSON.stringify(this.payload());
            this.autosaving = true;
            this.saveState = 'saving';

            fetch(this.autosaveUrl, {
                method: this.draftId ? 'PUT' : 'POST',
                credentials: 'same-origin',
                // keepalive lets the request outlive the page, but caps the body at 64KB.
                keepalive: leavingPage && json.length < 60000,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: json,
            })
                .then((r) => {
                    if (r.status === 419) this.refreshCsrfToken();
                    if (!r.ok) throw new Error(r.status);
                    return r.json();
                })
                .then((data) => {
                    if (!data.saved) { this.saveState = ''; return; }
                    this.lastSavedJson = json;
                    if (!this.draftId) this.adoptDraft(data);
                    this.savedAt = new Date(data.saved_at);
                    this.saveState = 'saved';
                    // The server has everything this browser has, so the local copy can go.
                    if (!this.hasUnsavedChanges()) {
                        clearTimeout(this.localTimer);
                        try { localStorage.removeItem(this.storageKey); } catch (e) {}
                    }
                })
                .catch(() => { this.saveState = 'failed'; })
                .finally(() => {
                    this.autosaving = false;
                    this.lastAutosaveAt = Date.now();
                    if (this.pendingSubmit) {
                        this.pendingSubmit = false;
                        this.$nextTick(() => this.$refs.form.requestSubmit());
                    }
                });
        },

        // The first background save of a new letter creates a draft; from
        // then on, everything (including Save draft and Seal & send) updates it.
        adoptDraft(data) {
            this.draftId = data.id;
            this.formAction = data.update_url;
            this.autosaveUrl = data.autosave_url;
            try { localStorage.removeItem(this.storageKey); } catch (e) {}
            this.storageKey = storageKeyPrefix + data.id;
            this.saveLocal();
            history.replaceState(history.state, '', data.edit_url);
        },

        onSubmit(event) {
            // Let an in-flight autosave finish first, or a new letter could
            // be created twice: once by the autosave, once by this submit.
            if (this.autosaving) {
                event.preventDefault();
                this.pendingSubmit = true;
                return;
            }
            this.submitting = true;
            this.$dispatch('letter-submitting');
        },

        saveStatus() {
            if (this.saveState === 'saving') return messages.saving;
            if (this.saveState === 'failed') return messages.saveFailed;
            if (this.saveState === 'saved') {
                const time = this.savedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                return messages.savedAt.replace(':time', time);
            }
            return '';
        },

        refreshCsrfToken() {
            return fetch(urls.csrfToken, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : null))
                .then((data) => {
                    if (!data?.token) return;
                    document.querySelectorAll('input[name=_token]').forEach((input) => { input.value = data.token; });
                    document.querySelector('meta[name=csrf-token]')?.setAttribute('content', data.token);
                })
                .catch(() => {});
        },

        // --- The browser's own copy -------------------------------------------------

        saveLocal() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify({ ...this.snapshot(), savedAt: Date.now() }));
            } catch (e) {}
        },

        restoreLocal() {
            let stored = null;
            try {
                stored = JSON.parse(localStorage.getItem(this.storageKey));
            } catch (e) {}

            if (!stored || typeof stored.body !== 'string' || stored.body === '' || stored.body === this.body) return;
            // Older than what the server has (e.g. edited since on another device).
            if (letter.updatedAt && stored.savedAt < letter.updatedAt) return;

            this.body = stored.body;
            this.query = stored.query ?? this.query;
            this.lastSelectedQuery = this.query;
            this.recipientId = stored.recipientId ?? this.recipientId;
            this.enclosureUrls = Array.isArray(stored.enclosureUrls) ? stored.enclosureUrls : this.enclosureUrls;
            this.restoredLocally = true;
        },

        discardLocal() {
            try { localStorage.removeItem(this.storageKey); } catch (e) {}
            Object.assign(this, {
                ...this.serverCopy,
                lastSelectedQuery: this.serverCopy.query,
                enclosureUrls: [...this.serverCopy.enclosureUrls],
            });
            this.restoredLocally = false;
            // The watchers fire after this tick; undo the local save they schedule.
            setTimeout(() => clearTimeout(this.localTimer));
        },

        // --- Choosing a recipient and sending ----------------------------------------

        search() {
            if (this.query !== this.lastSelectedQuery) {
                this.recipientId = '';
            }

            const query = this.query;
            if (query.length < 1) { this.results = []; this.open = false; return; }
            fetch(`${urls.search}?q=${encodeURIComponent(query)}`)
                .then((r) => r.json())
                .then((data) => {
                    if (query !== this.query) return; // a newer search is on its way
                    this.results = data;
                    this.open = data.length > 0;
                    this.selectExactMatch();
                });
        },

        selectExactMatch() {
            const typed = this.query.trim().toLowerCase();
            const exact = this.results.filter((user) => user.name.trim().toLowerCase() === typed);
            if (exact.length === 1 && !this.recipientId) {
                this.recipientId = exact[0].id;
                this.lastSelectedQuery = this.query;
            }
        },

        select(user) {
            this.recipientId = user.id;
            this.query = user.name;
            this.lastSelectedQuery = user.name;
            this.open = false;
            this.results = [];
        },

        requestSend() {
            if (!this.recipientId) this.selectExactMatch();

            this.recipientProblem = '';
            this.bodyProblem = '';

            if (!this.recipientId) {
                this.recipientProblem = this.query.trim() ? messages.recipientNotFound : messages.recipientMissing;
                this.$refs.recipient.focus();
                return;
            }

            if (!this.body.trim()) {
                this.bodyProblem = messages.bodyEmpty;
                this.$refs.body.focus();
                return;
            }

            this.$dispatch('open-modal', 'confirm-send');
        },
    };
}
