<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class PasswordResetController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('forgot-password');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        ResetPasswordNotification::createUrlUsing(fn ($user, string $token): string => route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        Password::broker('users')->sendResetLink(
            $request->only('email')
        );

        return redirect('/login')->with('status', __('If that email exists, we sent a reset link.'));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $status = Password::broker('users')->reset(
            Arr::only($request->all(), ['email', 'password', 'password_confirmation', 'token']),
            function ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                ]);

                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect('/login')->with('status', __('Your password has been reset. You may log in.'));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
