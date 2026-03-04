<?php

namespace App\Http\Requests\Admin;

use App\Actions\AllowList\StudentCodeParser;
use App\Models\ProfessionalSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAllowListEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $studentCode = $this->input('student_code');

        $data = [];

        if (is_string($email)) {
            $data['email'] = Str::lower(trim($email));
        }

        if (is_string($studentCode)) {
            $data['student_code'] = trim($studentCode);
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('admin.gestion.allow_list.import') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('allow_list_entries', 'email')->ignore($this->route('allow_list_entry')),
            ],
            'professional_school_id' => [
                'required',
                'integer',
                Rule::exists(ProfessionalSchool::class, 'id'),
            ],
            'student_code' => ['required', 'string', 'max:32', 'regex:/^\d{2,32}$/'],
        ];
    }

    public function derivedBaseYear(): int
    {
        return (int) StudentCodeParser::baseYear((string) $this->validated('student_code'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $schoolId = (int) $this->validated('professional_school_id');

            /** @var ProfessionalSchool|null $school */
            $school = ProfessionalSchool::query()
                ->with(['faculty:id,active'])
                ->find($schoolId);

            if ($school === null || ! $school->active || ! $school->faculty?->active) {
                $validator->errors()->add('professional_school_id', 'La escuela seleccionada no está disponible.');

                return;
            }

            $baseYear = StudentCodeParser::baseYear((string) $this->validated('student_code'));
            if ($baseYear === null) {
                $validator->errors()->add('student_code', 'No se pudo derivar la base desde el código.');

                return;
            }

            if ($baseYear < (int) $school->base_year_min || $baseYear > (int) $school->base_year_max) {
                $validator->errors()->add('student_code', "La base derivada ({$baseYear}) no es válida para la escuela seleccionada.");
            }
        });
    }
}
