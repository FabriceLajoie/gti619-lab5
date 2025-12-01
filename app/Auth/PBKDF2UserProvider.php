<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Services\PBKDF2PasswordHasher;

class PBKDF2UserProvider extends EloquentUserProvider
{
    protected $passwordHasher;

    public function __construct($hasher, $model, PBKDF2PasswordHasher $passwordHasher)
    {
        parent::__construct($hasher, $model);
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Validate a user with credentials
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plain = $credentials['password'];

        // Use PBKDF2 password verification
        return $this->passwordHasher->verify(
            $plain,
            $user->password_salt,
            $user->password,
            100000
        );
    }
}