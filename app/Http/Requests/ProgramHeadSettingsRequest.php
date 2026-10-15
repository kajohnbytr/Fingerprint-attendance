<?php

namespace App\Http\Requests;

use App\Security\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramHeadSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'program_head';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'current_password' => ['required', 'current_password'],
            'password' => [...PasswordPolicy::rules($this->user(), false), 'confirmed'],
            'school_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', Rule::in(['First semester', 'Second semester', 'Summer'])],
            'report_days' => ['required', 'integer', Rule::in([7, 30, 60, 90])],
            'notify_announcements' => ['nullable', 'boolean'],
            'notify_complaints' => ['nullable', 'boolean'],
            'notify_schedule_changes' => ['nullable', 'boolean'],
        ];
    }
}
