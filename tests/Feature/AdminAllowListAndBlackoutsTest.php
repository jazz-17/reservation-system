<?php

use App\Actions\AllowList\AllowListImportService;
use App\Models\AllowListEntry;
use App\Models\AuditEvent;
use App\Models\Blackout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

test('allow-list import supports merge mode (csv) with report + audit', function () {
    $admin = User::factory()->admin()->create();

    AllowListEntry::factory()->create(['email' => 'existing@example.edu']);

    $csv = implode("\n", [
        'existing@example.edu',
        'new@example.edu',
        'new@example.edu',
        'not-an-email',
    ]);

    $path = tempnam(sys_get_temp_dir(), 'allow-list-');
    file_put_contents($path, $csv);

    $file = new UploadedFile($path, 'emails.csv', 'text/csv', null, true);

    $report = app(AllowListImportService::class)->import($admin, $file, 'merge');

    expect($report['imported'])->toBe(2);
    expect($report['duplicates'])->toBe(1);
    expect($report['invalid'])->toBe(1);

    expect(AllowListEntry::query()->count())->toBe(2);
    expect(AllowListEntry::query()->where('email', 'existing@example.edu')->exists())->toBeTrue();
    expect(AllowListEntry::query()->where('email', 'new@example.edu')->exists())->toBeTrue();

    expect(AuditEvent::query()->where('event_type', 'allow_list.imported')->exists())->toBeTrue();
});

test('allow-list import supports replace mode (csv)', function () {
    $admin = User::factory()->admin()->create();

    AllowListEntry::factory()->create(['email' => 'old@example.edu']);
    AllowListEntry::factory()->create(['email' => 'old2@example.edu']);

    $path = tempnam(sys_get_temp_dir(), 'allow-list-');
    file_put_contents($path, "only@example.edu\n");

    $file = new UploadedFile($path, 'emails.csv', 'text/csv', null, true);

    app(AllowListImportService::class)->import($admin, $file, 'replace');

    expect(AllowListEntry::query()->count())->toBe(1);
    expect(AllowListEntry::query()->where('email', 'only@example.edu')->exists())->toBeTrue();
});

test('allow-list import supports xlsx uploads', function () {
    $admin = User::factory()->admin()->create();

    Storage::disk('local')->makeDirectory('tmp-tests');

    $export = new class implements FromArray
    {
        public function array(): array
        {
            return [
                ['one@example.edu'],
                ['two@example.edu'],
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
