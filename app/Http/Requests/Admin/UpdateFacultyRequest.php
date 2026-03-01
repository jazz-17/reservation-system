<?php

namespace App\Http\Requests\Admin;

use App\Models\Faculty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacultyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin.gestion.facultades.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Faculty|null $faculty */
        $faculty = $this->route('faculty');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('faculties', 'name')->ignore($faculty)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
