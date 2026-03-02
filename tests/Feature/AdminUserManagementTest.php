<?php

use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('admin users page renders', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->has('users.data', 2)
            ->has('available_roles')
            ->where('filters.search', '')
        );
});

test('operator cannot access user management', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admin can update user roles (student is excluded when staff selected) and audits', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.roles.update', $target), [
            'roles' => ['student', 'operator'],
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->hasRole('operator'))->toBeTrue();
    expect($target->hasRole('student'))->toBeFalse();

    expect(AuditEvent::query()
        ->where('event_type', 'user.roles_updated')
        ->where('actor_id', $admin->id)
        ->where('subject_type', User::class)
        ->where('subject_id', $target->id)
        ->exists()
    )->toBeTrue();
});

test('admin can disable and enable a user, clears sessions, and audits', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'session-1',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => time(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.status.update', $target), [
            'disabled' => true,
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->disabled_at)->not->toBeNull();
    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);

    expect(AuditEvent::query()->where('event_type', 'user.disabled')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.users.status.update', $target), [
            'disabled' => false,
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->disabled_at)->toBeNull();

    expect(AuditEvent::query()->where('event_type', 'user.enabled')->exists())->toBeTrue();
});

test('disabled users cannot log in', function () {
    $user = User::factory()->create([
        'disabled_at' => now(),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('admin can send password reset and verification emails and audits', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $target = User::factory()->unverified()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset.store', $target))
        ->assertRedirect();

    Notification::assertSentTo($target, ResetPassword::class);
    expect(AuditEvent::query()->where('event_type', 'user.password_reset_sent')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.users.email-verification.store', $target))
        ->assertRedirect();

    Notification::assertSentTo($target, QueuedVerifyEmail::class);
    expect(AuditEvent::query()->where('event_type', 'user.verification_sent')->exists())->toBeTrue();
});

test('cannot change roles of protected user', function () {
    $admin = User::factory()->admin()->create();
    $protected = User::factory()->admin()->create(['is_protected' => true]);

    $this->actingAs($admin)
        ->put(route('admin.users.roles.update', $protected), [
            'roles' => ['operator'],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('roles');

    $protected->refresh();
    expect($protected->hasRole('admin'))->toBeTrue();
    expect($protected->hasRole('operator'))->toBeFalse();
});

test('cannot disable protected user', function () {
    $admin = User::factory()->admin()->create();
    $protected = User::factory()->admin()->create(['is_protected' => true]);

    $this->actingAs($admin)
        ->put(route('admin.users.status.update', $protected), [
            'disabled' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('disabled');

    $protected->refresh();
    expect($protected->disabled_at)->toBeNull();
});

test('cannot send password reset to protected user', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $protected = User::factory()->admin()->create(['is_protected' => true]);

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset.store', $protected))
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    Notification::assertNotSentTo($protected, ResetPassword::class);
});

test('protected user is visible in users list with is_protected flag', function () {
    $admin = User::factory()->admin()->create();
    $protected = User::factory()->create(['is_protected' => true]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->has('users.data', 2)
            ->where('users.data.0.is_protected', true)
        );
});
