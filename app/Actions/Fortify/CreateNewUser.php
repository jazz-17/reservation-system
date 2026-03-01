<?php

namespace App\Actions\Fortify;

use App\Actions\AllowList\StudentCodeParser;
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

        $validator = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
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

        $entry = null;
        $school = null;
        $validator->after(function ($validator) use ($normalizedEmail, &$entry, &$school): void {
            if ($normalizedEmail === '' || ! Str::endsWith($normalizedEmail, '@unmsm.edu.pe')) {
                return;
            }

            $entry = AllowListEntry::query()
                ->where('email', $normalizedEmail)
                ->first(['email', 'student_code', 'professional_school_id', 'base_year']);

            if ($entry === null) {
                return;
            }

            if ($entry->professional_school_id === null || $entry->base_year === null || $entry->student_code === null) {
                $validator->errors()->add('email', 'Este correo no tiene datos completos (código/escuela/base). Contacta al administrador.');

                return;
            }

            $school = ProfessionalSchool::query()
                ->with(['faculty:id,active'])
                ->find((int) $entry->professional_school_id);

            if ($school === null || ! $school->active || ! $school->faculty?->active) {
                $validator->errors()->add('email', 'La escuela asignada a tu correo no está disponible. Contacta al administrador.');

                return;
            }

            $derivedBaseYear = StudentCodeParser::baseYear($entry->student_code);
            if ($derivedBaseYear === null || $derivedBaseYear !== (int) $entry->base_year) {
                $validator->errors()->add('email', 'Tu registro institucional tiene datos inconsistentes (base/código). Contacta al administrador.');

                return;
            }

            if ((int) $entry->base_year < $school->base_year_min || (int) $entry->base_year > $school->base_year_max) {
                $validator->errors()->add('email', 'La base asignada a tu correo no es válida para tu escuela. Contacta al administrador.');
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

        if (! $entry instanceof AllowListEntry) {
            throw ValidationException::withMessages(['email' => 'Este correo no está autorizado para registrarse.']);
        }

        $user = User::create([
            'name' => "{$input['first_name']} {$input['last_name']}",
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'professional_school_id' => $entry->professional_school_id,
            'base_year' => $entry->base_year,
            'phone' => $input['phone'] ?? null,
            'email' => $normalizedEmail,
            'student_code' => $entry->student_code,
            'password' => $input['password'],
        ]);

        $user->assignRole('student');

        return $user;
    }
}
