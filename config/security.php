<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PBKDF2 Password Hashing Configuration
    |--------------------------------------------------------------------------
    |
    | These options control the PBKDF2 password hashing implementation.
    | The iteration count should be high enough to make brute force attacks
    | computationally expensive while remaining reasonable for normal use.
    |
    */

    'pbkdf2_iterations' => env('PBKDF2_ITERATIONS', 100000),

    /*
    |--------------------------------------------------------------------------
    | Password History Configuration
    |--------------------------------------------------------------------------
    |
    | These options control password history tracking to prevent password reuse.
    |
    */

    'password_history_count' => env('PASSWORD_HISTORY_COUNT', 5),

    /*
    |--------------------------------------------------------------------------
    | Authentication Security Configuration
    |--------------------------------------------------------------------------
    |
    | These options control authentication security features like account
    | lockouts and failed attempt tracking.
    |
    */

    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
    'lockout_duration_minutes' => env('LOCKOUT_DURATION_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Password Policy Configuration
    |--------------------------------------------------------------------------
    |
    | These options control password complexity requirements.
    |
    */

    'password_min_length' => env('PASSWORD_MIN_LENGTH', 12),
    'password_require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
    'password_require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
    'password_require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
    'password_require_special' => env('PASSWORD_REQUIRE_SPECIAL', true),
    'password_expiry_days' => env('PASSWORD_EXPIRY_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Session Security Configuration
    |--------------------------------------------------------------------------
    |
    | These options control session security features.
    |
    */

    'session_timeout_minutes' => env('SESSION_TIMEOUT_MINUTES', 30),

];