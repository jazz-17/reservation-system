<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringBlackoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin.gestion.blackouts.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $startsOn = $this->input('starts_on');
        $endsOn = $this->input('ends_on');
        $reason = $this->input('reason');

        $this->merge([
            'starts_on' => is_string($startsOn) && $startsOn === '' ? null : $startsOn,
            'ends_on' => is_string($endsOn) && $endsOn === '' ? null : $endsOn,
            'reason' => is_string($reason) && $reason === '' ? null : $reason,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weekday' => ['required', 'integer', 'min:0', 'max:6'],
            'starts_time' => ['required', 'date_format:H:i'],
            'ends_time' => ['required', 'date_format:H:i', 'after:starts_time'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
