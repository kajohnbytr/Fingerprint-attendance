<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('IT###??'),
            'description' => 'Introduction to Computing',
            'lecture_units' => 3,
            'laboratory_units' => 1,
            'day' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'room' => 'Lab 1',
            'faculty_id' => null,
        ];
    }
}
