<?php

namespace App\Http\Controllers;


use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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



    protected function save(Request $request, Message $message): RedirectResponse
    {
        $intent = $request->input('intent', 'draft');
        abort_unless(in_array($intent, ['draft', 'send'], true), 422);
        $wantsToSend = $intent === 'send';

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

        $enclosureRules = [
            'enclosures' => ['nullable', 'array', 'max:5'],
            'enclosures.*' => ['url', 'max:2048'],
        ];
        $enclosureMessages = [
            'enclosures.*.url' => __('One of those links doesn\'t look right.'),
        ];

        if (! $wantsToSend) {
            $validated = $request->validate([
                'recipient_id' => [
                    'nullable', 'integer',
                    Rule::exists('users', 'id')->whereNull('deleted_at'),
                    Rule::notIn([$request->user()->id]),
                ],
                'body' => ['nullable', 'string', 'max:'.config('pennypost.max_letter_length')],
                ...$enclosureRules,
            ], [
                'recipient_id.exists' => $notPennyPostMember,
                'body.max' => __('You have run out of ink.'),
                ...$enclosureMessages,
            ]);

            $message->sender_id = $request->user()->id;
            $message->fill([
                'recipient_id' => $validated['recipient_id'] ?? null,
                'body' => $validated['body'] ?? '',
                'enclosures' => ! empty($validated['enclosures']) ? $validated['enclosures'] : null,
                'is_draft' => true,
                'scheduled_for' => null,
                'sent_at' => null,
            ])->save();

            return redirect()->route('messages.edit', $message)->with('status', 'draft-saved');
        }

        $validated = $request->validate([
            'recipient_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
                Rule::notIn([$request->user()->id]),
            ],
            'body' => ['required', 'string', 'max:'.config('pennypost.max_letter_length')],
            ...$enclosureRules,
        ], [
            'recipient_id.required' => $notPennyPostMember,
            'recipient_id.exists' => $notPennyPostMember,
            'recipient_id.not_in' => "You can't send a message to yourself.", // TODO: rethink this - you can send messages to yourself in WhatsApp, why not here?
            'body.max' => __('You have run out of ink.'),
            ...$enclosureMessages,
        ]);

        $message->sender_id = $request->user()->id;
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
            ->with('deliveryDayName', $message->scheduled_for->format('l'))
            ->with('deliveryDayOrdinal', $message->scheduled_for->format('jS'));
    }
}
