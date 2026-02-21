<?php

namespace App\Actions\Fortify;

use App\Actions\Audit\Audit;
use App\Concerns\PasswordValidationRules;
use App\Models\AllowListEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $normalizedEmail = Str::lower((string) ($input['email'] ?? ''));

        $validator = Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'professional_school' => ['required', 'string', 'max:255'],
            'base' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $email = Str::lower((string) $value);

                    if (! AllowListEntry::query()->where('email', $email)->exists()) {
                        $fail('Este correo no está autorizado para registrarse.');
                    }
                },
            ],
            'password' => $this->passwordRules(),
        ]);

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

        return User::create([
            'name' => "{$input['first_name']} {$input['last_name']}",
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'professional_school' => $input['professional_school'],
            'base' => $input['base'],
            'phone' => $input['phone'] ?? null,
            'email' => $normalizedEmail,
            'password' => $input['password'],
        ]);
    }
}
