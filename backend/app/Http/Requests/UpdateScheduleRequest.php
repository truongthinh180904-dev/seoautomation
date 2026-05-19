<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['keyword_processing', 'publishing', 'report'])],
            'cron_expression' => ['sometimes', 'nullable', 'string', 'max:100'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'paused', 'completed', 'failed'])],
            'config' => ['sometimes', 'nullable', 'array'],
            'last_run_at' => ['sometimes', 'nullable', 'date'],
            'next_run_at' => ['sometimes', 'nullable', 'date'],
            'run_count' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
