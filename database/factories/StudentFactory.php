<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => 'S' . fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'level' => fake()->randomElement([100, 200, 300, 400]),
            'academic_section' => fake()->randomElement(['SE-1A', 'SE-2A', 'SE-3A', 'SE-4A']),
            'student_type' => 'regular',
            'status' => 'active',
            'enrollment_date' => now(),
        ];
    }
}
