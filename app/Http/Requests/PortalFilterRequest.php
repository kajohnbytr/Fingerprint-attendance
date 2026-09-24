<?php

namespace App\Http\Requests;

use App\AttendanceSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PortalFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'faculty';
    }

    protected function prepareForValidation(): void
    {
        $days = app(AttendanceSummary::class)->preferences($this->user())['report_days'];
        $this->merge([
            'from' => $this->input('from') ?: now()->subDays($days - 1)->toDateString(),
            'to' => $this->input('to') ?: now()->toDateString(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'student_id' => ['nullable', 'integer', 'min:1'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'block_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['present', 'absent', 'late', 'pending'])],
            'type' => ['nullable', Rule::in(['attendance', 'enrollment'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['from', 'to'])
                && CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 92) {
                $validator->errors()->add('from', 'Choose a period of no more than 93 days.');
            }
        }];
    }
}
