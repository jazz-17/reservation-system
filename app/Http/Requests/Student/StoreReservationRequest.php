<?php

namespace App\Http\Requests\Student;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.required' => 'La hora de inicio es obligatoria.',
            'starts_at.date' => 'La hora de inicio no es una fecha válida.',
            'ends_at.required' => 'La hora de fin es obligatoria.',
            'ends_at.date' => 'La hora de fin no es una fecha válida.',
            'ends_at.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
        ];
    }
}
