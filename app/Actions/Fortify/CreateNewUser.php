<?php

namespace App\Actions\Fortify;

use App\Actions\Audit\Audit;
use App\Concerns\PasswordValidationRules;
use App\Models\AllowListEntry;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $normalizedEmail = Str::lower((string) ($input['email'] ?? ''));

        $selectedSchoolId = (int) ($input['professional_school_id'] ?? 0);

        $validator = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'professional_school_id' => [
                'required',
                'integer',
                Rule::exists(ProfessionalSchool::class, 'id')->where('active', true),
            ],
            'base_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $email = Str::lower((string) $value);

                    if (! Str::endsWith($email, '@unmsm.edu.pe')) {
                        $fail('Usa tu correo institucional @unmsm.edu.pe.');

                        return;
                    }

                    if (! AllowListEntry::query()->where('email', $email)->exists()) {
                        $fail('Este correo no está autorizado para registrarse.');
                    }
                },
            ],
            'password' => $this->passwordRules(),
        ]);

        $school = null;
        $validator->after(function ($validator) use ($selectedSchoolId, $input, $normalizedEmail, &$school): void {
            if ($selectedSchoolId <= 0) {
                return;
            }

            $school = ProfessionalSchool::query()
                ->with(['faculty:id,active'])
                ->find($selectedSchoolId);

            if ($school === null || ! $school->active || ! $school->faculty?->active) {
                $validator->errors()->add('professional_school_id', 'La escuela seleccionada no está disponible.');

                return;
            }

            $baseYear = (int) ($input['base_year'] ?? 0);
            if ($baseYear < $school->base_year_min || $baseYear > $school->base_year_max) {
                $validator->errors()->add('base_year', 'La base seleccionada no está disponible para la escuela.');
            }

            if ($normalizedEmail === '' || ! Str::endsWith($normalizedEmail, '@unmsm.edu.pe')) {
                return;
            }

            $entry = AllowListEntry::query()
                ->where('email', $normalizedEmail)
                ->first(['email', 'professional_school_id', 'base_year']);

            if ($entry === null) {
                return;
            }

            if ($entry->professional_school_id === null || $entry->base_year === null) {
                $validator->errors()->add('email', 'Este correo no tiene escuela/base asignada. Contacta al administrador.');

                return;
            }

            if ((int) $entry->professional_school_id !== $selectedSchoolId) {
                $validator->errors()->add('professional_school_id', 'La escuela seleccionada no coincide con tu registro institucional.');
            }

            if ((int) $entry->base_year !== $baseYear) {
                $validator->errors()->add('base_year', 'La base seleccionada no coincide con tu registro institucional.');
            }
        });

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            if ($normalizedEmail !== '' && ! AllowListEntry::query()->where('email', $normalizedEmail)->exists()) {
                Audit::record('allow_list.registration_rejected', actor: null, subject: null, metadata: [
                    'email' => $normalizedEmail,
                    'ip' => app(Request::class)->ip(),
                ]);
            }

            throw $exception;
        }

        $user = User::create([
            'name' => "{$input['first_name']} {$input['last_name']}",
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'professional_school_id' => $selectedSchoolId,
            'base_year' => (int) $input['base_year'],
            'phone' => $input['phone'] ?? null,
            'email' => $normalizedEmail,
            'password' => $input['password'],
        ]);

        $user->assignRole('student');

        return $user;
    }
}
