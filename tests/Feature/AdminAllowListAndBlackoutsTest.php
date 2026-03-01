<?php

use App\Models\AllowListEntry;
use App\Models\AuditEvent;
use App\Models\Blackout;
use App\Models\Faculty;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('allow-list page renders', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.allow-list.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AllowList')
            ->has('count')
            ->has('entries')
            ->has('schools')
        );
});

test('allow-list create page renders', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.allow-list.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AllowListCreate')
            ->has('schools')
        );
});

test('admins can store an allow-list entry (code + derived base)', function () {
    $admin = User::factory()->admin()->create();

    $faculty = Faculty::factory()->create(['active' => true]);
    $school = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'code' => 'ep_sistemas',
        'active' => true,
        'base_year_min' => 2020,
        'base_year_max' => 2024,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.allow-list.store'), [
            'email' => 'new@unmsm.edu.pe',
            'professional_school_id' => $school->id,
            'student_code' => '22000001',
        ])
        ->assertRedirect();

    $entry = AllowListEntry::query()->where('email', 'new@unmsm.edu.pe')->firstOrFail();
    expect($entry->student_code)->toBe('22000001');
    expect((int) $entry->base_year)->toBe(2022);
    expect((int) $entry->professional_school_id)->toBe((int) $school->id);

    expect(AuditEvent::query()->where('event_type', 'allow_list.entry_saved')->exists())->toBeTrue();
});

test('store rejects an allow-list entry when derived base is out of school range', function () {
    $admin = User::factory()->admin()->create();

    $faculty = Faculty::factory()->create(['active' => true]);
    $school = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'code' => 'ep_sistemas',
        'active' => true,
        'base_year_min' => 2020,
        'base_year_max' => 2021,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.allow-list.store'), [
            'email' => 'bad@unmsm.edu.pe',
            'professional_school_id' => $school->id,
            'student_code' => '22000001',
        ])
        ->assertSessionHasErrors('student_code');
});

test('artisan padron import populates allow-list entries (replace mode) and audits', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@unmsm.edu.pe',
    ]);

    $faculty = Faculty::factory()->create(['active' => true]);

    $systems = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'code' => 'ep_sistemas',
        'active' => true,
        'base_year_min' => 2020,
        'base_year_max' => 2024,
    ]);

    $software = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'code' => 'ep_software',
        'active' => true,
        'base_year_min' => 2020,
        'base_year_max' => 2024,
    ]);

    AllowListEntry::factory()->create([
        'email' => 'old@unmsm.edu.pe',
        'student_code' => '20000000',
        'professional_school_id' => $systems->id,
        'base_year' => 2020,
    ]);

    $csv = implode("\n", [
        'codigo,base,correo,programa',
        '22000011,22,one@unmsm.edu.pe,E.P. de Ingeniería de Sistemas',
        '23000012,23,two@unmsm.edu.pe,E.P. de Ingeniería de Software',
        '',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'padron-');
    file_put_contents($path, $csv);

    $this->artisan('allow-list:import-padron', [
        'path' => $path,
        '--mode' => 'replace',
        '--admin-email' => 'admin@unmsm.edu.pe',
    ])->assertExitCode(0);

    expect(AllowListEntry::query()->count())->toBe(2);
    expect(AllowListEntry::query()->where('email', 'old@unmsm.edu.pe')->exists())->toBeFalse();

    $one = AllowListEntry::query()->where('email', 'one@unmsm.edu.pe')->firstOrFail();
    expect($one->student_code)->toBe('22000011');
    expect((int) $one->base_year)->toBe(2022);
    expect((int) $one->professional_school_id)->toBe((int) $systems->id);
    expect((int) $one->imported_by)->toBe((int) $admin->id);

    $two = AllowListEntry::query()->where('email', 'two@unmsm.edu.pe')->firstOrFail();
    expect($two->student_code)->toBe('23000012');
    expect((int) $two->base_year)->toBe(2023);
    expect((int) $two->professional_school_id)->toBe((int) $software->id);
    expect((int) $two->imported_by)->toBe((int) $admin->id);

    expect(AuditEvent::query()->where('event_type', 'allow_list.imported')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

test('allow-list index returns paginated entries with search', function () {
    $admin = User::factory()->admin()->create();

    $faculty = Faculty::factory()->create(['active' => true]);
    $school = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'active' => true,
        'base_year_min' => 2020,
        'base_year_max' => 2025,
    ]);

    AllowListEntry::factory()->count(3)->create([
        'professional_school_id' => $school->id,
        'base_year' => 2022,
    ]);
    AllowListEntry::factory()->create([
        'email' => 'searchme@unmsm.edu.pe',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.allow-list.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AllowList')
            ->has('entries.data', 4)
            ->has('filters')
        );

    $this->actingAs($admin)
        ->get(route('admin.allow-list.index', ['search' => 'searchme']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AllowList')
            ->has('entries.data', 1)
        );
});

test('admins can update an allow-list entry', function () {
    $admin = User::factory()->admin()->create();

    $faculty = Faculty::factory()->create(['active' => true]);
    $school = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'active' => true,
        'base_year_min' => 2020,
        'base_year_max' => 2025,
    ]);

    $entry = AllowListEntry::factory()->create([
        'email' => 'original@unmsm.edu.pe',
        'student_code' => '22000001',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.allow-list.update', $entry), [
            'email' => 'updated@unmsm.edu.pe',
            'professional_school_id' => $school->id,
            'student_code' => '23000002',
        ])
        ->assertRedirect();

    $entry->refresh();
    expect($entry->email)->toBe('updated@unmsm.edu.pe');
    expect($entry->student_code)->toBe('23000002');
    expect((int) $entry->base_year)->toBe(2023);

    expect(AuditEvent::query()->where('event_type', 'allow_list.entry_updated')->exists())->toBeTrue();
});

test('admins can delete an allow-list entry', function () {
    $admin = User::factory()->admin()->create();

    $entry = AllowListEntry::factory()->create([
        'email' => 'todelete@unmsm.edu.pe',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.allow-list.destroy', $entry))
        ->assertRedirect();

    expect(AllowListEntry::query()->where('email', 'todelete@unmsm.edu.pe')->exists())->toBeFalse();
    expect(AuditEvent::query()->where('event_type', 'allow_list.entry_deleted')->exists())->toBeTrue();
});

test('admins can create and delete blackouts (audited)', function () {
    $admin = User::factory()->admin()->create();

    $timezone = 'America/Lima';
    $startsAt = CarbonImmutable::now($timezone)->addDays(3)->setTime(9, 0);
    $endsAt = $startsAt->addHours(2);

    $this->actingAs($admin)
        ->post(route('admin.blackouts.store'), [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'reason' => 'Mantenimiento',
        ])
        ->assertRedirect();

    $blackout = Blackout::query()->firstOrFail();
    expect(AuditEvent::query()->where('event_type', 'blackout.created')->where('subject_id', $blackout->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('admin.blackouts.destroy', $blackout))
        ->assertRedirect();

    expect(Blackout::query()->count())->toBe(0);
    expect(AuditEvent::query()->where('event_type', 'blackout.deleted')->where('metadata->blackout_id', $blackout->id)->exists())->toBeTrue();
});
