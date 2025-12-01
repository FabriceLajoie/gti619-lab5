<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    /**
     * name of the factory's corresponding model
     *
     * @var string
     */
    protected $model = AuditLog::class;

    /**
     * define the model's default state
     *
     * @return array
     */
    public function definition()
    {
        $eventTypes = [
            'login_success',
            'login_failed',
            'account_locked',
            'account_unlocked',
            'user_logout',
            'password_changed',
            'password_policy_violation',
            'role_changed',
            'security_config_changed',
            'unauthorized_access',
            'user_created',
            'session_created',
            'session_destroyed',
        ];

        return [
            'event_type' => $this->faker->randomElement($eventTypes),
            'user_id' => User::factory(),
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'details' => [
                'message' => $this->faker->sentence,
                'additional_data' => $this->faker->word,
            ],
        ];
    }

    /**
     * audit log is for a successful login
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function loginSuccess()
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'login_success',
                'details' => [
                    'message' => 'User successfully authenticated',
                ],
            ];
        });
    }

    /**
     *  failed login
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function loginFailed()
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'login_failed',
                'user_id' => null,
                'details' => [
                    'email' => $this->faker->email,
                    'message' => 'Failed authentication attempt',
                ],
            ];
        });
    }

    /**
     * account lockout
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function accountLocked()
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'account_locked',
                'details' => [
                    'failed_attempts' => $this->faker->numberBetween(3, 10),
                    'message' => 'Account locked due to excessive failed login attempts',
                ],
            ];
        });
    }

    /**
     * password change
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function passwordChanged()
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'password_changed',
                'details' => [
                    'forced' => $this->faker->boolean,
                    'message' => 'Password changed by user',
                ],
            ];
        });
    }

    /**
     * unauthorized access
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unauthorizedAccess()
    {
        return $this->state(function (array $attributes) {
            return [
                'event_type' => 'unauthorized_access',
                'details' => [
                    'resource' => $this->faker->word,
                    'action' => $this->faker->randomElement(['view', 'create', 'update', 'delete']),
                    'message' => 'Unauthorized access attempt',
                ],
            ];
        });
    }

    /**
     * high severity
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function highSeverity()
    {
        return $this->state(function (array $attributes) {
            $highSeverityEvents = [
                'account_locked',
                'unauthorized_access',
                'password_policy_violation',
                'session_hijack_detected',
            ];

            return [
                'event_type' => $this->faker->randomElement($highSeverityEvents),
            ];
        });
    }

    /**
     * medium severity
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function mediumSeverity()
    {
        return $this->state(function (array $attributes) {
            $mediumSeverityEvents = [
                'login_failed',
                'password_changed',
                'role_changed',
                'security_config_changed',
                'user_created',
            ];

            return [
                'event_type' => $this->faker->randomElement($mediumSeverityEvents),
            ];
        });
    }

    /**
     * low severity
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function lowSeverity()
    {
        return $this->state(function (array $attributes) {
            $lowSeverityEvents = [
                'login_success',
                'user_logout',
                'session_created',
                'session_destroyed',
            ];

            return [
                'event_type' => $this->faker->randomElement($lowSeverityEvents),
            ];
        });
    }

    /**
     * no associated user
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withoutUser()
    {
        return $this->state(function (array $attributes) {
            return [
                'user_id' => null,
            ];
        });
    }
}