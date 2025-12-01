<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $permissions = [
            ['name' => 'view_dashboard', 'resource' => 'dashboard', 'action' => 'view'],
            ['name' => 'view_settings', 'resource' => 'settings', 'action' => 'view'],
            ['name' => 'manage_users', 'resource' => 'users', 'action' => 'manage'],
            ['name' => 'manage_security_config', 'resource' => 'security', 'action' => 'manage'],
            ['name' => 'view_residential_clients', 'resource' => 'clients', 'action' => 'view_residential'],
            ['name' => 'view_business_clients', 'resource' => 'clients', 'action' => 'view_business'],
            ['name' => 'manage_residential_clients', 'resource' => 'clients', 'action' => 'manage_residential'],
            ['name' => 'manage_business_clients', 'resource' => 'clients', 'action' => 'manage_business'],
            ['name' => 'view_audit_logs', 'resource' => 'audit', 'action' => 'view'],
            ['name' => 'unlock_accounts', 'resource' => 'accounts', 'action' => 'unlock'],
        ];

        $permission = $this->faker->randomElement($permissions);

        return [
            'name' => $permission['name'],
            'resource' => $permission['resource'],
            'action' => $permission['action'],
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Create a specific permission by name.
     *
     * @param string $name
     * @param string $resource
     * @param string $action
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function withName($name, $resource = null, $action = null)
    {
        return $this->state(function (array $attributes) use ($name, $resource, $action) {
            return [
                'name' => $name,
                'resource' => $resource ?? $attributes['resource'],
                'action' => $action ?? $attributes['action'],
            ];
        });
    }
}