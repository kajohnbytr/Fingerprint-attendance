<?php

namespace App\Http\Requests;

use App\Security\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($account)],
            'role' => ['required', Rule::in(['admin', 'program_head', 'faculty', 'student'])],
            'is_active' => ['required', 'boolean'],
            'student_id' => [
                Rule::requiredIf($this->input('role') === 'student'),
                Rule::prohibitedIf($this->input('role') !== 'student'),
                'nullable', 'integer',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
                Rule::unique('users', 'student_id')->ignore($account),
            ],
            'password' => [...($account ? PasswordPolicy::rules($account, false) : PasswordPolicy::baseRules()), 'confirmed'],
        ];
    }
}
