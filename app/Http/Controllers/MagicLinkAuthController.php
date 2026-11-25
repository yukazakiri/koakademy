<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\SendMagicLinkRequest;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class MagicLinkAuthController extends Controller
{
    /**
     * Display the magic link sign-in request page.
     */
    public function show(): Response
    {
        return Inertia::render('magic-link');
    }

    /**
     * Send a signed magic login link to the user's email address.
     */
    public function send(SendMagicLinkRequest $request): RedirectResponse
    {
        $email = $request->string('email')->trim()->lower()->toString();
        $remember = $request->boolean('remember');

        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user instanceof User) {
            $token = Str::random(40);
            $expiresMinutes = 15;

            // Cache token with single-use atomic retrieval
            Cache::put("magic_link:{$user->id}:{$token}", true, now()->addMinutes($expiresMinutes));

            $signedUrl = URL::temporarySignedRoute(
                'magic-link.verify',
                now()->addMinutes($expiresMinutes),
                [
                    'user' => $user->id,
                    'token' => $token,
                    'remember' => $remember ? 1 : 0,
                ]
            );

            $user->notify(new MagicLoginLinkNotification(
                url: $signedUrl,
                expiresInMinutes: $expiresMinutes,
                requestIp: $request->ip(),
            ));
        }

        return back()->with('status', 'If an account exists for this email, we have sent a secure sign-in link.');
    }

    /**
     * Validate the signed magic link without consuming it (protects against
     * corporate/university email link scanners pre-fetching GET URLs), then
     * render a confirmation page that submits a POST to complete sign-in.
     */
    public function verify(Request $request, User $user): Response|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors([
                'email' => 'This sign-in link is invalid or has expired. Please request a new one.',
            ]);
        }

        if ($user->suspended_at !== null) {
            abort(403, 'Account access is unavailable. Please contact an administrator.');
        }

        $token = (string) $request->query('token');
        $cacheKey = "magic_link:{$user->id}:{$token}";

        if (! Cache::has($cacheKey)) {
            return redirect()->route('login')->withErrors([
                'email' => 'This sign-in link has already been used or has expired. Please request a new one.',
            ]);
        }

        $remember = $request->boolean('remember');
        $consumeUrl = URL::temporarySignedRoute(
            'magic-link.consume',
            now()->addMinutes(5),
            [
                'user' => $user->id,
                'token' => $token,
                'remember' => $remember ? 1 : 0,
            ]
        );

        return Inertia::render('magic-link-confirm', [
            'email' => $this->maskEmail($user->email),
            'consumeUrl' => $consumeUrl,
        ]);
    }

    /**
     * Atomically consume the single-use magic link token on POST and authenticate the user.
     */
    public function consume(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors([
                'email' => 'This sign-in link is invalid or has expired. Please request a new one.',
            ]);
        }

        if ($user->suspended_at !== null) {
            abort(403, 'Account access is unavailable. Please contact an administrator.');
        }

        $token = (string) $request->query('token');
        $cacheKey = "magic_link:{$user->id}:{$token}";

        // Atomic retrieval & deletion protects against replay attacks
        $isTokenValid = Cache::pull($cacheKey);

        if (! $isTokenValid) {
            return redirect()->route('login')->withErrors([
                'email' => 'This sign-in link has already been used or has expired. Please request a new one.',
            ]);
        }

        $remember = $request->boolean('remember');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Check for enabled 2FA login challenges
        if ($user->requiresTwoFactorChallenge()) {
            Auth::logout();
            $request->session()->put('auth.2fa.id', $user->id);
            $request->session()->put('auth.2fa.remember', $remember);

            return redirect()->route('two-factor.login');
        }

        return redirect()->intended($user->homeUrl())->with('status', 'Welcome back! You have been securely signed in.');
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.'•••@'.$domain;
    }
}
