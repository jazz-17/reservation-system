<?php

use App\Models\User;

test('authenticated users can access the calendar page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/mi-calendario')
        ->assertSuccessful();
});

test('guests are redirected to login from the calendar page', function () {
    $this->get('/mi-calendario')
        ->assertRedirect('/login');
});

test('public calendar remains accessible without authentication', function () {
    $this->get('/calendario')
        ->assertSuccessful();
});
