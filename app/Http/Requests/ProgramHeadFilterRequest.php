<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class ProgramHeadFilterRequest extends PortalFilterRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'program_head';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'faculty_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', Rule::in(['attendance', 'enrollment', 'faculty_load'])],
        ];
    }
}
