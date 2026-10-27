<?php

namespace App\Http\Controllers;


use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageController extends Controller
{
    /**
     * One tile per person the current user has a correspondence with:
     * anyone they've exchanged delivered letters with, plus anyone
     * they've sealed a letter to that's still awaiting delivery. Someone
     * else's letter to you that hasn't been delivered yet never counts —
     * that would spoil the whole "no peeking before post day" premise.
     */
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        $messages = Message::query()
            ->visibleTo($userId)
            ->with(['sender', 'recipient'])
            ->orderByDesc('sent_at')
            ->get(['id', 'sender_id', 'recipient_id', 'sent_at']);

        // Because $messages is already sorted newest-first, the first
        // message groupBy() encounters for each "other person" key is
        // necessarily that thread's most recent letter — so the groups
        // come out in the right order for free.
        $correspondences = $messages
            ->groupBy(fn (Message $m) => $m->sender_id === $userId ? $m->recipient_id : $m->sender_id)
            ->map(function ($thread) use ($userId) {
                $latest = $thread->first();

                return (object) [
                    'person' => $latest->sender_id === $userId ? $latest->recipient : $latest->sender,
                    'letterCount' => $thread->count(),
                ];
            })
            ->values();

        $drafts = $request->user()
            ->sentMessages()
            ->drafts()
            ->count();

        return view('correspondence.index', [
            'correspondences' => $correspondences,
            'draftCount' => $drafts,
        ]);
    }

    /**
     * The thread with one person, newest letter first: every delivered
     * letter between you two, plus any of your own sealed-but-undelivered
     * letters to them, and your unsent drafts to them. Their undelivered
     * letters to you are excluded — those stay hidden until post day.
     *
     * Renders the whole thread on one page (no pagination yet) — the
     * query itself is shaped so a character-budget cursor can be added
     * later without changing this method's contract.
     */
    public function show(Request $request, User $person): View
    {
        $userId = $request->user()->id;

        abort_if($person->id === $userId, 404);


        $letters = Message::query()
            ->visibleTo($userId)
            ->between($userId, $person)
            ->orderByDesc('sent_at')
            ->get();

        return view('correspondence.show', [
            'person' => $person,
            'letters' => $letters,
            'drafts' => $request->user()
                ->sentMessages()
                ->drafts()
                ->where('recipient_id', $person->id)
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function drafts(Request $request): View
    {
        $drafts = $request->user()
            ->sentMessages()
            ->drafts()
            ->with('recipient')
            ->latest('updated_at')
            ->paginate(15);

        return view('messages.drafts', ['drafts' => $drafts]);
    }

    public function create(Request $request): View
    {
        return view('messages.create', [
            'letter' => new Message,
            'nextBatch' => Message::nextBatchFor(),
            'backTo' => $request->filled('to_id') ? User::find($request->integer('to_id')) : null,
        ]);
    }

    public function edit(Request $request, Message $message): View
    {
        abort_unless(
            $message->sender_id === $request->user()->id && $message->is_draft,
            404
        );

        return view('messages.create', [
            'letter' => $message,
            'nextBatch' => Message::nextBatchFor(),
            'backTo' => $message->recipient,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Message(['sender_id' => $request->user()->id]));
    }

    public function update(Request $request, Message $message): RedirectResponse
    {
        abort_unless(
            $message->sender_id === $request->user()->id && $message->is_draft,
            404
        );

        return $this->save($request, $message);
    }

    public function destroy(Request $request, Message $message): RedirectResponse
    {
        abort_unless(
            $message->sender_id === $request->user()->id && $message->is_draft,
            404
        );

        $message->delete();

        return redirect()->route('messages.drafts')->with('status', 'draft-deleted');
    }



    /**
     * Saving and sending share one rule: whatever the user typed must end
     * up somewhere they can find it. A draft save never fails over the
     * recipient (a bad one is dropped with a warning), and a send that
     * fails validation is stored as a draft before we bounce them back.
     */
    protected function save(Request $request, Message $message): RedirectResponse
    {
        $intent = $request->input('intent', 'draft');
        abort_unless(in_array($intent, ['draft', 'send'], true), 422);
        $wantsToSend = $intent === 'send';
        $userId = $request->user()->id;
        $wasNew = ! $message->exists;

        $notPennyPostMember = "That doesn't look like a Penny Post member — pick someone from the suggestions.";

        // Blank rows from the "add another link" UI shouldn't count as
        // enclosures, and shouldn't trip url validation either.
        $request->merge([
            'enclosures' => collect($request->input('enclosures', []))
                ->map(fn ($url) => trim((string) $url))
                ->filter()
                ->values()
                ->all(),
        ]);

        // Someone who typed a name but never clicked a suggestion still
        // meant that person — as long as the name is unambiguous.
        $typedName = trim((string) $request->input('recipient_name', ''));
        if (! $request->filled('recipient_id') && $typedName !== '') {
            $request->merge(['recipient_id' => $this->resolveRecipientByName($typedName, $userId)?->id]);
        }

        $recipientId = $this->validRecipientId($request->input('recipient_id'), $userId);

        $enclosureRules = [
            'enclosures' => ['nullable', 'array', 'max:5'],
            'enclosures.*' => ['url', 'max:2048'],
        ];
        $enclosureMessages = [
            'enclosures.*.url' => __('One of those links doesn\'t look right.'),
        ];

        if (! $wantsToSend) {
            $validated = $request->validate([
                'body' => ['nullable', 'string', 'max:'.config('pennypost.max_letter_length')],
                ...$enclosureRules,
            ], [
                'body.max' => __('You have run out of ink.'),
                ...$enclosureMessages,
            ]);

            $this->storeDraft($message, $userId, $recipientId, $validated['body'] ?? '', $validated['enclosures'] ?? []);

            $redirect = redirect()
                ->route('messages.edit', $message)
                ->with('status', 'draft-saved')
                ->with('clearLocalDraft', $this->localDraftKeys($message, $wasNew));

            $askedFor = $request->filled('recipient_id') || $typedName !== '';
            if ($recipientId === null && $askedFor) {
                $redirect->withInput(['recipient_name' => $typedName]);

                if ((string) $request->input('recipient_id') === (string) $userId) {
                    $redirect->withErrors(['recipient_id' => __("You can't send a message to yourself.")]);
                } else {
                    $redirect->with('recipientWarning', $typedName !== ''
                        ? __("We couldn't find ':name' — pick them from the suggestions.", ['name' => $typedName])
                        : __($notPennyPostMember));
                }
            }

            return $redirect;
        }

        $validator = Validator::make($request->all(), [
            'recipient_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                Rule::notIn([$userId]),
            ],
            'body' => ['required', 'string', 'max:'.config('pennypost.max_letter_length')],
            ...$enclosureRules,
        ], [
            'recipient_id.required' => $typedName !== ''
                ? __("We couldn't find ':name' — pick them from the suggestions.", ['name' => $typedName])
                : $notPennyPostMember,
            'recipient_id.exists' => $notPennyPostMember,
            'recipient_id.not_in' => "You can't send a message to yourself.", // TODO: rethink this - you can send messages to yourself in WhatsApp, why not here?
            'body.max' => __('You have run out of ink.'),
            ...$enclosureMessages,
        ]);

        if ($validator->fails()) {
            // Not sent — but the text must survive even if they close the tab now.
            $body = $request->input('body');
            $this->storeDraft(
                $message,
                $userId,
                $recipientId,
                is_string($body) ? $body : '',
                $this->validEnclosures($request->input('enclosures', [])),
            );

            return redirect()
                ->route('messages.edit', $message)
                ->withErrors($validator)
                ->withInput([...$request->except('_token', 'intent'), 'recipient_id' => $recipientId])
                ->with('status', 'send-failed')
                ->with('clearLocalDraft', $this->localDraftKeys($message, $wasNew));
        }

        $validated = $validator->validated();

        $message->sender_id = $userId;
        $message->fill([
            'recipient_id' => $validated['recipient_id'],
            'body' => $validated['body'],
            'enclosures' => ! empty($validated['enclosures']) ? $validated['enclosures'] : null,
            'is_draft' => false,
            'scheduled_for' => Message::nextBatchFor(),
            'sent_at' => now(),
        ])->save();

        return redirect()
            ->route('correspondence.show', $message->recipient)
            ->with('status', 'message-sent')
            ->with('clearLocalDraft', $this->localDraftKeys($message, $wasNew))
            ->with('deliveryDayName', $message->scheduled_for->format('l'))
            ->with('deliveryDayOrdinal', $message->scheduled_for->format('jS'));
    }

    protected function storeDraft(Message $message, int $userId, ?int $recipientId, string $body, array $enclosures): void
    {
        $message->sender_id = $userId;
        $message->fill([
            'recipient_id' => $recipientId,
            'body' => $body,
            'enclosures' => ! empty($enclosures) ? $enclosures : null,
            'is_draft' => true,
            'scheduled_for' => null,
            'sent_at' => null,
        ])->save();
    }

    /**
     * An active, onboarded member other than yourself whose name matches
     * exactly (ignoring case and surrounding spaces). Names aren't unique,
     * so if more than one person matches we don't guess.
     */
    protected function resolveRecipientByName(string $name, int $userId): ?User
    {
        $matches = User::query()
            ->whereKeyNot($userId)
            ->whereNotNull('name')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->limit(2)
            ->get(['id']);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    protected function validRecipientId(mixed $id, int $userId): ?int
    {
        if (! is_numeric($id) || (int) $id === $userId) {
            return null;
        }

        // SoftDeletes keeps deleted members out of this query.
        return User::whereKey((int) $id)->exists() ? (int) $id : null;
    }

    protected function validEnclosures(mixed $urls): array
    {
        return collect(is_array($urls) ? $urls : [])
            ->filter(fn ($url) => is_string($url) && Validator::make(['url' => $url], ['url' => ['url', 'max:2048']])->passes())
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * The localStorage suffixes the compose page used for this letter,
     * which the next page load clears once the server has the text.
     */
    protected function localDraftKeys(Message $message, bool $wasNew): array
    {
        return $wasNew ? ['new', (string) $message->id] : [(string) $message->id];
    }
}
