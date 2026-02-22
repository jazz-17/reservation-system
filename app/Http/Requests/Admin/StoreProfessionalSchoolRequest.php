<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfessionalSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', Rule::exists('faculties', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('professional_schools', 'name')->where(fn ($query) => $query->where('faculty_id', $this->integer('faculty_id'))),
            ],
            'base_year_min' => ['required', 'integer', 'min:2000', 'max:2100', 'lte:base_year_max'],
            'base_year_max' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:base_year_min'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
