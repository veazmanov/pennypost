<?php

namespace App\Http\Controllers;

use App\Actions\SendAccountDeletionLink;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Actions\AnonymizeUser;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $nextCatchableBatch = Message::nextBatchFor();
        $nextPickup = Message::cutoffFor($nextCatchableBatch);
        $nextDelivery = Message::nextDeliveryAt();

        return view('profile.edit', [
            'user' => $request->user(),
            'nextBatch' => $nextDelivery,
            'nextPickup' => $nextPickup,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Request account deletion — sends a confirmation link rather than
     * deleting immediately. The account is only removed once that link
     * is clicked, in confirmDestroy().
     */
    public function destroy(Request $request, SendAccountDeletionLink $sendAccountDeletionLink): RedirectResponse
    {
        $sendAccountDeletionLink($request->user());

        return Redirect::route('profile.edit')->with('status', 'account-deletion-requested');
    }

    /**
     * Actually delete the account, reached only via the signed link
     * emailed by destroy(). Logs out the current session if it belongs
     * to this user, and invalidates any other active sessions too.
     */

public function confirmDestroy(Request $request, User $user, AnonymizeUser $anonymizeUser): RedirectResponse
{
    if ($request->user()?->id === $user->id) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    $anonymizeUser($user);

    return redirect()->to('/')->with('status', 'account-deleted');
}

    /**
     * Everything this user is entitled to take with them: their account
     * info, every letter they've sent (delivered or still sealed), every
     * letter delivered to them, and any drafts still sitting unsent.
     * Someone else's undelivered letter to this user is deliberately
     * excluded — same rule the correspondence views follow.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();


$sent = $user->sentMessages()->with('recipient')->orderBy('created_at')->get()
    ->map(fn ($m) => [
        'to' => $m->recipient->name ?? __('Deleted Account'),
        'body' => $m->body,
        'enclosures' => $m->enclosures ?? [],
        'is_draft' => $m->is_draft,
        'scheduled_for' => $m->scheduled_for?->toIso8601String(),
        'sent_at' => $m->sent_at?->toIso8601String(),
        'delivered_at' => $m->delivered_at?->toIso8601String(),
    ]);

$received = $user->receivedMessages()->delivered()->with('sender')->orderBy('delivered_at')->get()
    ->map(fn ($m) => [
        'from' => $m->sender->name ?? __('Deleted Account'),
        'body' => $m->body,
        'enclosures' => $m->enclosures ?? [],
        'delivered_at' => $m->delivered_at?->toIso8601String(),
    ]);

        $export = [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'member_since' => $user->created_at->toIso8601String(),
            ],
            'sent_and_draft_letters' => $sent,
            'received_letters' => $received,
        ];

        $filename = 'pennypost-export-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($export) {
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
