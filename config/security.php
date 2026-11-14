<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Throttling
    |--------------------------------------------------------------------------
    |
    | Limits applied to the unauthenticated authentication surface: password
    | sign-in, two-factor challenges, passkey sign-in, signup verification
    | codes and password reset requests.
    |
    | The per-account lockout is keyed on "email|ip" on purpose. Keying it on
    | the account alone would let anyone lock every account in the school out
    | of its own session by sending five wrong passwords per address, while
    | keying it on the address alone would let an attacker walk away from a
    | lockout simply by changing networks. Scoping the lock to the pair means
    | the lock follows the attacker and leaves the legitimate owner alone.
    |
    */

    /*
    | Requests per minute allowed for a single email address on the password
    | sign-in endpoint.
    */
    'login_rate_limit' => (int) env('AUTH_LOGIN_RATE_LIMIT', 5),

    /*
    | Requests per minute allowed from a single IP address on the password
    | sign-in endpoint. This is the limit that stops password spraying, where
    | one wrong password is tried against a different account every time.
    | Raise it for deployments behind a shared campus network.
    */
    'login_ip_rate_limit' => (int) env('AUTH_LOGIN_IP_RATE_LIMIT', 30),

    /*
    | Failed sign-ins allowed for one email address and IP pair before the
    | account is locked for that pair.
    */
    'login_max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),

    /*
    | Minutes an account stays locked after the failed sign-in limit is hit.
    */
    'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),

    /*
    | Requests per minute allowed on the two-factor challenge endpoint.
    */
    'two_factor_rate_limit' => (int) env('AUTH_TWO_FACTOR_RATE_LIMIT', 10),

    /*
    | Requests per minute allowed on the two-factor email delivery endpoint.
    | Kept low because every request mails a code to the account owner.
    */
    'two_factor_email_rate_limit' => (int) env('AUTH_TWO_FACTOR_EMAIL_RATE_LIMIT', 3),

    /*
    | Wrong two-factor codes allowed for a single challenge before the
    | challenge is destroyed and the visitor has to sign in again.
    */
    'two_factor_max_attempts' => (int) env('AUTH_TWO_FACTOR_MAX_ATTEMPTS', 5),

    /*
    | Requests per minute allowed on the passkey sign-in endpoints.
    */
    'passkey_rate_limit' => (int) env('AUTH_PASSKEY_RATE_LIMIT', 10),

    /*
    | Requests per minute allowed on the passwordless sign-in link endpoint.
    | Kept low because every request can mail a link to an arbitrary address.
    */
    'magic_link_rate_limit' => (int) env('AUTH_MAGIC_LINK_RATE_LIMIT', 5),

    /*
    | Requests per minute allowed on the password reset endpoints. Kept low
    | because every request can mail a reset link to an arbitrary address.
    */
    'password_reset_rate_limit' => (int) env('AUTH_PASSWORD_RESET_RATE_LIMIT', 5),

    /*
    | Wrong signup verification codes allowed before the issued code is
    | burned and a new one has to be requested.
    */
    'otp_max_attempts' => (int) env('AUTH_OTP_MAX_ATTEMPTS', 5),

];
