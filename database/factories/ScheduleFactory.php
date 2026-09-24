<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'block_id' => Block::factory(),
            'subject_code' => 'IT101',
            'subject_name' => 'Introduction to Computing',
            'day' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'Lab 1',
            'instructor' => fake()->name(),
        ];
    }
}
