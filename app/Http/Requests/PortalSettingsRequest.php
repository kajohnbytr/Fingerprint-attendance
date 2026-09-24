<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PortalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'faculty';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->user()->id)],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(12)],
            'late_minutes' => ['required', 'integer', 'between:0,60'],
            'report_days' => ['required', 'integer', Rule::in([7, 30, 60, 90])],
        ];
    }
}
