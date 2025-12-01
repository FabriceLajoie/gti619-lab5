<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * model's default state
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Administrateur',
                'Préposé aux clients résidentiels',
                'Préposé aux clients d\'affaire',
                'User'
            ]),
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Create admin role
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function administrator()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Administrateur',
                'description' => 'System administrator with full access',
            ];
        });
    }

    /**
     * residential client role
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function residentialClerk()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Préposé aux clients résidentiels',
                'description' => 'Handles residential client accounts',
            ];
        });
    }

    /**
     * business client role
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function businessClerk()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Préposé aux clients d\'affaire',
                'description' => 'Handles business client accounts',
            ];
        });
    }
}