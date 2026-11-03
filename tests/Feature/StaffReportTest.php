<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StaffReportTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'Staff', string $branch = 'Jakarta'): User
    {
        return User::factory()->create(['role' => $role, 'branch' => $branch]);
    }

    private function assignment(User $staff, User $reviewer, string $status, string $received = '2026-11-02 05:00:00'): int
    {
        $task = DB::table('work_tasks')->insertGetId([
            'creator_id' => $reviewer->id, 'branch' => $staff->branch,
            'title' => 'Tugas '.$status, 'description' => 'Aktivitas manual', 'platform' => 'Instagram',
            'target_url' => 'https://www.instagram.com/p/contoh/', 'activities' => '["like"]',
            'account_count' => 1, 'evidence_instructions' => 'Screenshot', 'priority' => 'Normal',
            'starts_at' => $received, 'due_at' => '2026-11-30 05:00:00', 'created_at' => $received, 'updated_at' => $received,
        ]);

        return DB::table('task_assignments')->insertGetId([
            'task_id' => $task, 'user_id' => $staff->id, 'reviewer_id' => $reviewer->id,
            'status' => $status, 'created_at' => $received, 'updated_at' => $received,
        ]);
    }

    public function test_report_counts_received_approved_pending_and_accounts_in_local_period(): void
    {
        $staff = $this->member();
        $manager = $this->member('Kacab');
        $this->assignment($staff, $manager, 'Disetujui');
        $this->assignment($staff, $manager, 'Menunggu verifikasi');
        $this->assignment($staff, $manager, 'Belum dikerjakan');
        $this->assignment($staff, $manager, 'Ditolak');
        $this->assignment($staff, $manager, 'Dalam proses', '2026-10-31 17:00:00');
        $this->assignment($staff, $manager, 'Disetujui', '2026-10-31 16:59:59');
        $this->assignment($this->member(), $manager, 'Disetujui');
        DB::table('social_accounts')->insert([
            ['user_id' => $staff->id, 'platform' => 'Instagram', 'name' => 'Akun baru', 'identity' => '@baru', 'created_at' => '2026-11-02 05:00:00'],
            ['user_id' => $staff->id, 'platform' => 'YouTube', 'name' => 'Akun lama', 'identity' => '@lama', 'created_at' => '2026-10-30 05:00:00'],
        ]);
        $this->actingAs($staff)->get('/reports?from=2026-11-01&to=2026-11-03')
            ->assertOk()->assertViewHas('summary', [
                'received' => 5, 'completed' => 1, 'waiting' => 1, 'unfinished' => 3, 'rejected' => 1, 'accounts' => 1,
            ])->assertSee('Akun baru')->assertDontSee('Akun lama');
    }

    public function test_reports_are_private_to_staff_and_managers_branch(): void
    {
        $staff = $this->member();
        $other = $this->member();
        $outside = $this->member('Staff', 'Bandung');
        $manager = $this->member('Kacab');
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/pdf')->assertRedirect('/login');
        $this->actingAs($staff)->get('/reports?user_id='.$other->id)->assertNotFound();
        $this->get('/reports/pdf?user_id='.$other->id)->assertNotFound();
        $this->actingAs($manager)->get('/reports?user_id='.$staff->id)->assertOk();
        $this->get('/reports?user_id='.$outside->id)->assertNotFound();
        $this->get('/reports/pdf?user_id='.$outside->id)->assertNotFound();
        $this->actingAs($this->member('Superadmin'))->get('/reports?user_id='.$outside->id)->assertOk();
    }

    public function test_invalid_and_reversed_periods_are_rejected(): void
    {
        $staff = $this->member();
        $this->actingAs($staff)->get('/reports?from=not-a-date')->assertSessionHasErrors('from');
        $this->get('/reports?from=2026-11-04&to=2026-11-01')->assertSessionHasErrors([
            'to' => 'Periode harus berurutan dan maksimal 366 hari.',
        ]);
        $this->get('/reports/pdf?from=2020-01-01&to=2026-11-01')->assertSessionHasErrors('to');
    }

    public function test_pdf_embeds_latest_evidence_escapes_notes_and_cleans_temporary_files(): void
    {
        Storage::fake('local');
        $staff = $this->member();
        $manager = $this->member('Kacab');
        $assignment = $this->assignment($staff, $manager, 'Disetujui');
        $old = DB::table('task_submissions')->insertGetId(['assignment_id' => $assignment, 'revision' => 1, 'reported_activities' => '[]', 'notes' => 'Revisi lama rahasia', 'created_at' => '2026-11-02 06:00:00']);
        $latest = DB::table('task_submissions')->insertGetId(['assignment_id' => $assignment, 'revision' => 2, 'reported_activities' => '[]', 'notes' => '<script>alert(1)</script>', 'created_at' => '2026-11-02 07:00:00']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('local')->put('evidence/latest.png', $png);
        DB::table('task_evidence')->insert([
            ['submission_id' => $old, 'path' => 'evidence/old.png', 'mime' => 'image/png', 'original_name' => 'old.png'],
            ['submission_id' => $latest, 'path' => 'evidence/latest.png', 'mime' => 'image/png', 'original_name' => 'latest.png'],
            ['submission_id' => $latest, 'path' => 'evidence/missing.png', 'mime' => 'image/png', 'original_name' => 'missing.png'],
        ]);
        Process::fake();
        $before = File::isDirectory(storage_path('app/private/report-temp')) ? File::directories(storage_path('app/private/report-temp')) : [];
        $this->actingAs($staff)->get('/reports?from=2026-11-01&to=2026-11-03')
            ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('old.png')->assertDontSee('Revisi lama rahasia');
        $response = $this->get('/reports/pdf?from=2026-11-01&to=2026-11-03')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('laporan-staf-'.$staff->id.'-2026-11-01-2026-11-03.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('/Subtype /Image', $response->getContent());
        $this->assertSame($before, File::directories(storage_path('app/private/report-temp')));
        Process::assertNothingRan();
        Storage::disk('local')->assertExists('evidence/latest.png');
    }

    public function test_empty_report_downloads_without_browser_or_shell_process(): void
    {
        Process::fake();
        $response = $this->actingAs($this->member())->get('/reports/pdf?from=2026-11-01&to=2026-11-03')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('%%EOF', $response->getContent());
        Process::assertNothingRan();
    }

    public function test_pdf_storage_failure_returns_actionable_error(): void
    {
        $staff = $this->member();
        File::partialMock()->shouldReceive('makeDirectory')->once()->andThrow(new \RuntimeException('Storage is unavailable.'));
        $this->actingAs($staff)->get('/reports/pdf?from=2026-11-01&to=2026-11-03')
            ->assertStatus(503)->assertSee('folder storage dapat ditulis');
    }

    public function test_oversized_evidence_requests_a_shorter_period_without_starting_browser(): void
    {
        Storage::fake('local');
        Process::fake();
        $staff = $this->member();
        $assignment = $this->assignment($staff, $this->member('Kacab'), 'Menunggu verifikasi');
        $submission = DB::table('task_submissions')->insertGetId([
            'assignment_id' => $assignment, 'revision' => 1, 'reported_activities' => '[]',
            'created_at' => '2026-11-02 06:00:00',
        ]);
        Storage::disk('local')->put('evidence/large.png', '');
        $handle = fopen(Storage::disk('local')->path('evidence/large.png'), 'c');
        ftruncate($handle, 40 * 1024 * 1024 + 1);
        fclose($handle);
        DB::table('task_evidence')->insert([
            'submission_id' => $submission, 'path' => 'evidence/large.png',
            'mime' => 'image/png', 'original_name' => 'large.png',
        ]);
        $this->actingAs($staff)->get('/reports/pdf?from=2026-11-01&to=2026-11-03')
            ->assertStatus(422)->assertSee('Pilih periode lebih pendek');
        Process::assertNothingRan();
        Storage::disk('local')->assertExists('evidence/large.png');
    }

    #[TestWith(['png'])]
    #[TestWith(['jpg'])]
    #[TestWith(['webp'])]
    public function test_pdf_includes_supported_screenshot_formats(string $format): void
    {
        Storage::fake('local');
        $staff = $this->member();
        $assignment = $this->assignment($staff, $this->member('Kacab'), 'Disetujui');
        $submission = DB::table('task_submissions')->insertGetId([
            'assignment_id' => $assignment, 'revision' => 1, 'reported_activities' => '[]',
            'created_at' => '2026-11-02 06:00:00',
        ]);
        $image = imagecreatetruecolor(40, 30);
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'jpg' => imagejpeg($image),
            'webp' => imagewebp($image),
        };
        $contents = ob_get_clean();
        $path = 'evidence/proof.'.$format;
        Storage::disk('local')->put($path, $contents);
        DB::table('task_evidence')->insert([
            'submission_id' => $submission, 'path' => $path,
            'mime' => $format === 'jpg' ? 'image/jpeg' : 'image/'.$format, 'original_name' => 'proof.'.$format,
        ]);

        $response = $this->actingAs($staff)->get('/reports/pdf?from=2026-11-01&to=2026-11-03')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('/Subtype /Image', $response->getContent());
        Storage::disk('local')->assertExists($path);
    }
}
