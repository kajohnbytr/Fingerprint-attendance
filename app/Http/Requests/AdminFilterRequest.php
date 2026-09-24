<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AdminFilterRequest extends PortalFilterRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'search' => ['nullable', 'string', 'max:100'],
            'faculty_id' => ['nullable', 'integer', 'min:1'],
            'role' => ['nullable', Rule::in(['admin', 'program_head', 'faculty', 'student'])],
            'complaint_status' => ['nullable', Rule::in(['open', 'in_review', 'resolved'])],
            'active' => ['nullable', Rule::in(['0', '1'])],
        ];
    }
}
