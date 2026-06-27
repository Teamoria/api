<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'industry' => fake()->randomElement(['Technology', 'Healthcare', 'Finance', 'Education', 'Retail', 'Manufacturing', 'Marketing', 'Consulting']),
            'website' => fake()->url(),
            'address' => fake()->address(),
            'logo_path' => null,
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
