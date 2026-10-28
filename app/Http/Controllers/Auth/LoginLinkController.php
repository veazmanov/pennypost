<?php

namespace App\Http\Controllers\Auth;

use App\Actions\SendLoginLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginLinkController extends Controller
{
    public function store(Request $request, SendLoginLink $sendLoginLink): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return $this->sendLink($request, $sendLoginLink, $validated['email']);
    }

    /**
     * Sign-up entry point. Functionally identical to store() — a name is
     * always collected later, in onboarding, so we don't ask for it twice.
     * Kept as a separate action/view purely so "Log in" and "Sign up" can
     * carry different copy for new vs. returning users.
     */
    public function register(Request $request, SendLoginLink $sendLoginLink): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return $this->sendLink($request, $sendLoginLink, $validated['email']);
    }

    public function authenticate(Request $request, User $user): RedirectResponse
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->name === null) {
            return redirect()->route('onboarding.name');
        }

        return redirect()->intended(route('correspondence.index', absolute: false));
    }


protected function sendLink(Request $request, SendLoginLink $sendLoginLink, string $rawEmail): RedirectResponse
{
    if ($request->filled('website')) {
        return redirect()->route('login')->with('status', 'login-link-sent');
    }

    $email = Str::lower($rawEmail);

    $ipThrottleKey = $email.'|'.$request->ip();
    if (RateLimiter::tooManyAttempts($ipThrottleKey, 3)) {
        throw ValidationException::withMessages([
            'email' => __('Please wait a moment before requesting another link.'),
        ]);
    }
    RateLimiter::hit($ipThrottleKey, 60);

    $user = User::where('email', $email)->first();

    if ($user === null && User::onlyTrashed()->where('email', $email)->exists()) {
        // This email belongs to a deleted account. Rather than crash trying
        // to insert a duplicate (or silently resurrect someone's old,
        // never-anonymized account), tell them plainly.
        throw ValidationException::withMessages([
            'email' => __("We couldn't find an active account for that email. If you previously deleted your account, you'll need to sign up again."),
        ]);
    }

    // Per-recipient throttle for addresses that have never clicked a link,
    // so the form can't be used to flood a stranger's inbox. Say so plainly
    // when it trips: pretending we sent a link leaves a new user waiting
    // for an email that is never coming.
    $isUnverified = $user === null || $user->email_verified_at === null;
    $emailThrottleKey = 'unverified-login-link:'.$email;

    if ($isUnverified && RateLimiter::tooManyAttempts($emailThrottleKey, 3)) {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($emailThrottleKey) / 60));

        throw ValidationException::withMessages([
            'email' => trans_choice(
                "We've already sent a few links to this address — please check your spam or junk folder. You can request another in :minutes minute.|We've already sent a few links to this address — please check your spam or junk folder. You can request another in :minutes minutes.",
                $minutes,
                ['minutes' => $minutes],
            ),
        ]);
    }

    if ($user === null) {
        try {
            $user = User::create(['email' => $email]);
        } catch (UniqueConstraintViolationException) {
            // Lost the race: another concurrent request (e.g. a double-clicked
            // submit) inserted this email a moment ago. Fetch what it created
            // instead of failing the whole request.
            $user = User::where('email', $email)->first();

            if ($user === null) {
                // Genuinely unexpected at this point — fail loudly rather
                // than pass null into SendLoginLink and 500 anyway.
                throw ValidationException::withMessages([
                    'email' => __('Something went wrong sending that link. Please try again in a moment.'),
                ]);
            }
        }
    }

    $sendLoginLink($user);

    // Only count links that actually went out, so a failed send doesn't
    // lock the user out of retrying.
    if ($isUnverified) {
        RateLimiter::hit($emailThrottleKey, 900);
    }

    return redirect()->route('login')->with('status', 'login-link-sent');
}
}
