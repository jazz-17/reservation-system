<?php

use App\Actions\AllowList\AllowListImportService;
use App\Models\AllowListEntry;
use App\Models\AuditEvent;
use App\Models\Blackout;
use App\Models\Faculty;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

test('allow-list import supports merge mode (csv) with report + audit', function () {
    $admin = User::factory()->admin()->create();

    $faculty = Faculty::factory()->create(['active' => true]);
    $school = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'code' => 'ep_sistemas',
        'active' => true,
    ]);

    AllowListEntry::factory()->create([
        'email' => 'existing@unmsm.edu.pe',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
    ]);

    $csv = implode("\n", [
        'existing@unmsm.edu.pe,ep_sistemas,B22',
        'new@unmsm.edu.pe,ep_sistemas,B22',
        'new@unmsm.edu.pe,ep_sistemas,B22',
        'not-an-email,ep_sistemas,B22',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'allow-list-');
    file_put_contents($path, $csv);

    $file = new UploadedFile($path, 'emails.csv', 'text/csv', null, true);

    $report = app(AllowListImportService::class)->import($admin, $file, 'merge');

    expect($report['imported'])->toBe(2);
    expect($report['duplicates'])->toBe(1);
    expect($report['invalid'])->toBe(1);

    expect(AllowListEntry::query()->count())->toBe(2);
    expect(AllowListEntry::query()->where('email', 'existing@unmsm.edu.pe')->exists())->toBeTrue();
    expect(AllowListEntry::query()->where('email', 'new@unmsm.edu.pe')->exists())->toBeTrue();

    expect(AuditEvent::query()->where('event_type', 'allow_list.imported')->exists())->toBeTrue();
});

test('allow-list import supports replace mode (csv)', function () {
    $admin = User::factory()->admin()->create();

    $faculty = Faculty::factory()->create(['active' => true]);
    $school = ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'code' => 'ep_sistemas',
        'active' => true,
    ]);

    AllowListEntry::factory()->create([
        'email' => 'old@unmsm.edu.pe',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
    ]);
    AllowListEntry::factory()->create([
        'email' => 'old2@unmsm.edu.pe',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'allow-list-');
    file_put_contents($path, "only@unmsm.edu.pe,ep_sistemas,B22\n");

    $file = new UploadedFile($path, 'emails.csv', 'text/csv', null, true);

    app(AllowListImportService::class)->import($admin, $file, 'replace');

    expect(AllowListEntry::query()->count())->toBe(1);
    expect(AllowListEntry::query()->where('email', 'only@unmsm.edu.pe')->exists())->toBeTrue();
});

test('allow-list import supports xlsx uploads', function () {
    $admin = User::factory()->admin()->create();

    $faculty = Faculty::factory()->create(['active' => true]);
    ProfessionalSchool::factory()->create([
        'faculty_id' => $faculty->id,
        'code' => 'ep_sistemas',
        'active' => true,
    ]);

    Storage::disk('local')->makeDirectory('tmp-tests');

    $export = new class implements FromArray
    {
        public function array(): array
        {
            return [
                ['one@unmsm.edu.pe', 'ep_sistemas', 'B22'],
                ['two@unmsm.edu.pe', 'ep_sistemas', 'B22'],
            ];
        }
    };

    Excel::store($export, 'tmp-tests/allow.xlsx', 'local');

    $xlsxPath = Storage::disk('local')->path('tmp-tests/allow.xlsx');
    $file = new UploadedFile(
        $xlsxPath,
        'allow.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );

    $report = app(AllowListImportService::class)->import($admin, $file, 'replace');

    expect($report['imported'])->toBe(2);
    expect(AllowListEntry::query()->count())->toBe(2);
});

test('admins can download allow-list csv template', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.allow-list.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertSee('email,school_code,base');
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
