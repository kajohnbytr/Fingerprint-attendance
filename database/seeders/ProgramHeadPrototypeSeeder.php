<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProgramHeadPrototypeSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@northbridge.test',
            ],
            [
                'name' => 'Elena Morgan',
                'role' => 'program_head',
                'password' => Hash::make(
                    'ChangeMe123!'
                ),
            ]
        );

        User::updateOrCreate(
            [
                'email' => 'faculty@northbridge.test',
            ],
            [
                'name' => 'Dr. James Patel',
                'role' => 'faculty',
                'password' => Hash::make(
                    'ChangeMe123!'
                ),
            ]
        );

        Block::firstOrCreate(
            [
                'name' => 'COMSEC 02',
            ],
            [
                'subject' => 'BS Information Technology',
                'year_level' => 3,
                'adviser' => 'Dr. James Patel',
            ]
        );

        $students = [
            [
                'student_number' => '2026-0001',
                'first_name' => 'Sophia',
                'last_name' => 'Chen',
                'email' => 'sophia@example.test',
            ],
            [
                'student_number' => '2026-0002',
                'first_name' => 'Miguel',
                'last_name' => 'Santos',
                'email' => 'miguel@example.test',
            ],
            [
                'student_number' => '2026-0003',
                'first_name' => 'Andrea',
                'last_name' => 'Reyes',
                'email' => 'andrea@example.test',
            ],
            [
                'student_number' => '2026-0004',
                'first_name' => 'Joshua',
                'last_name' => 'Garcia',
                'email' => 'joshua@example.test',
            ],
        ];

        foreach ($students as $student) {
            Student::firstOrCreate(
                [
                    'student_number' => $student['student_number'],
                ],
                [
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'email' => $student['email'],
                    'subject' => 'BS Information Technology',
                    'year_level' => 3,
                    'block_id' => null,
                    'status' => 'active',
                ]
            );
        }

        Subject::firstOrCreate(
            [
                'code' => 'ITE 381',
            ],
            [
                'description' => 'IT Business Solution',

                'lecture_units' => 2,
                'laboratory_units' => 1,
                'day' => 'Monday',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'room' => 'PTC 405',
                'faculty_id' => null,
            ]
        );
    }
}
