<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['keyword_processing', 'publishing', 'report'])],
            'cron_expression' => ['nullable', 'string', 'max:100'],
            'scheduled_at' => ['nullable', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'paused', 'completed', 'failed'])],
            'config' => ['nullable', 'array'],
            'next_run_at' => ['nullable', 'date'],
        ];
    }
}
