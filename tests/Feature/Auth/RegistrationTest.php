<?php

use App\Models\AllowListEntry;
use App\Models\ProfessionalSchool;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    AllowListEntry::factory()->create(['email' => 'test@example.com']);
    $school = ProfessionalSchool::factory()->create([
        'base_year_min' => 2020,
        'base_year_max' => 2024,
    ]);

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
        'phone' => '999999999',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
