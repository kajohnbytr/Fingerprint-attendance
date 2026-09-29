<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class FacultyController extends Controller
{
    public function index(Request $request): View
    {
        $faculty = User::where('role', 'faculty')
            ->when(
                $request->user()->role === 'faculty',
                fn ($query) => $query->whereKey($request->user()->id)
            )
            ->with([
                'assignedSubjects.enrollments.student',
                'assignedSubjects.enrollments.block',
            ])
            ->orderBy('name')
            ->get();

        return view('program_head.faculty', [
            'faculty' => $faculty,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'faculty',
            'password' => Hash::make('FACULTY-2026'),
            'must_change_password' => true,
        ]);

        return back()->with(
            'success',
            'Faculty member added successfully.'
        );
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if(
            $user->role !== 'faculty',
            403
        );

        $user->delete();

        return back()->with(
            'success',
            'Faculty member removed successfully.'
        );
    }
}
