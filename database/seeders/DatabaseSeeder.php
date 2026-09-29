<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         * Prototype data should only be created locally
         * or while running automated tests.
         */
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $this->call(SuperAdminSeeder::class);

        /*
        |--------------------------------------------------------------------------
        | Program Head account
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'email' => 'admin@northbridge.test',
            ],
            [
                'name' => 'Program Administrator',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'admin',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Faculty accounts
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'email' => 'james.patel@northbridge.test',
            ],
            [
                'name' => 'Dr. James Patel',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'faculty',
            ]
        );

        User::updateOrCreate(
            [
                'email' => 'maria.santos@northbridge.test',
            ],
            [
                'name' => 'Prof. Maria Santos',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'faculty',
            ]
        );

        User::updateOrCreate(
            [
                'email' => 'robert.cruz@northbridge.test',
            ],
            [
                'name' => 'Prof. Robert Cruz',
                'password' => Hash::make('ChangeMe123!'),
                'role' => 'faculty',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Fixed 4th Year section
        |--------------------------------------------------------------------------
        */

        $section = Block::updateOrCreate(
            [
                'name' => 'COMSEC 02',
            ],
            [
                'subject' => 'BS Information Technology',
                'year_level' => 4,
                'adviser' => 'Dr. James Patel',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Fixed 4th Year student list
        |--------------------------------------------------------------------------
        |
        | These are dummy students for the prototype. Developers can replace
        | these records with the real student data later.
        */

        $students = [
            [
                'student_number' => '2026-0001',
                'first_name' => 'Sophia',
                'last_name' => 'Chen',
                'email' => 'sophia.chen@student.test',
            ],
            [
                'student_number' => '2026-0002',
                'first_name' => 'Miguel',
                'last_name' => 'Santos',
                'email' => 'miguel.santos@student.test',
            ],
            [
                'student_number' => '2026-0003',
                'first_name' => 'Andrea',
                'last_name' => 'Reyes',
                'email' => 'andrea.reyes@student.test',
            ],
            [
                'student_number' => '2026-0004',
                'first_name' => 'Joshua',
                'last_name' => 'Garcia',
                'email' => 'joshua.garcia@student.test',
            ],
            [
                'student_number' => '2026-0005',
                'first_name' => 'Angela',
                'last_name' => 'Dela Cruz',
                'email' => 'angela.delacruz@student.test',
            ],
            [
                'student_number' => '2026-0006',
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza',
                'email' => 'carlos.mendoza@student.test',
            ],
            [
                'student_number' => '2026-0007',
                'first_name' => 'Patricia',
                'last_name' => 'Lim',
                'email' => 'patricia.lim@student.test',
            ],
            [
                'student_number' => '2026-0008',
                'first_name' => 'Nathan',
                'last_name' => 'Flores',
                'email' => 'nathan.flores@student.test',
            ],
        ];

        foreach ($students as $studentData) {
            Student::updateOrCreate(
                [
                    'student_number' => $studentData['student_number'],
                ],
                [
                    'first_name' => $studentData['first_name'],

                    'last_name' => $studentData['last_name'],

                    'email' => $studentData['email'],

                    'subject' => 'BS Information Technology',

                    'year_level' => 4,

                    'block_id' => null,

                    'status' => 'active',
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Fixed subject list
        |--------------------------------------------------------------------------
        |
        | The Program Head cannot create or delete these subjects.
        | The developers maintain this list through seeders or data imports.
        */

        Subject::updateOrCreate(
            [
                'code' => 'ITE 381',
            ],
            [
                'description' => 'IT Business Solution',
                'lecture_units' => 2,
                'laboratory_units' => 1,
                'day' => 'Monday',
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'room' => 'PTC 405',
                'faculty_id' => null,
            ]
        );
    }
}
