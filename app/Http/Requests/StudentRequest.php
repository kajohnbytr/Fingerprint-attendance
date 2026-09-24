<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'student_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('students')->ignore($student),
            ],

            'first_name' => [
                'required',
                'string',
                'max:80',
                "regex:/^[\pL\pM .'-]+$/u",
            ],

            'last_name' => [
                'required',
                'string',
                'max:80',
                "regex:/^[\pL\pM .'-]+$/u",
            ],

            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('students')->ignore($student),
            ],

            'subject' => [
                'required',
                'string',
                'max:100',
            ],

            'year_level' => [
                'required',
                'integer',
                'between:1,6',
            ],

            'block_id' => [
                'nullable',
                'integer',
                'exists:blocks,id',
            ],

            'status' => [
                'required',
                'in:active,inactive,graduated',
            ],
        ];
    }
}
