<?php

return [

    'pbkdf2_iterations' => env('PBKDF2_ITERATIONS', 100000),

    'password_history_count' => env('PASSWORD_HISTORY_COUNT', 5),

    'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', 5),
    'lockout_duration_minutes' => env('LOCKOUT_DURATION_MINUTES', 15),

    'password_min_length' => env('PASSWORD_MIN_LENGTH', 12),
    'password_require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
    'password_require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
    'password_require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
    'password_require_special' => env('PASSWORD_REQUIRE_SPECIAL', true),
    'password_expiry_days' => env('PASSWORD_EXPIRY_DAYS', 90),

    'session_timeout_minutes' => env('SESSION_TIMEOUT_MINUTES', 30),

];