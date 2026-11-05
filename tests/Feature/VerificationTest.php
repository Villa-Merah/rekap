<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private function submission(User $recipient, User $reviewer, string $title = 'Tugas pemeriksaan', string $status = 'Menunggu verifikasi'): object
    {
        $task = DB::table('work_tasks')->insertGetId([
            'creator_id' => $reviewer->id, 'branch' => $recipient->branch, 'title' => $title,
            'description' => 'Instruksi tugas', 'platform' => 'Instagram', 'target_url' => 'https://instagram.com/p/test',
            'activities' => '["like"]', 'account_count' => 1, 'priority' => 'Normal', 'evidence_instructions' => 'Screenshot',
            'starts_at' => '2026-11-01 00:00:00', 'due_at' => '2026-11-30 00:00:00',
            'created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00',
        ]);
        $assignment = DB::table('task_assignments')->insertGetId(['task_id' => $task, 'user_id' => $recipient->id, 'reviewer_id' => $reviewer->id,
            'status' => $status, 'created_at' => '2026-11-01 00:00:00', 'updated_at' => '2026-11-01 00:00:00']);
        $submission = DB::table('task_submissions')->insertGetId(['assignment_id' => $assignment, 'revision' => 1, 'reported_activities' => '[]',
            'status' => $status, 'created_at' => '2026-11-04 05:00:00']);

        return (object) compact('task', 'assignment', 'submission');
    }

    public function test_guest_must_login(): void
    {
        $this->get('/verifications')->assertRedirect('/login');
        $this->get('/verifications/1')->assertRedirect('/login');
    }

    #[TestWith(['Staff'])]
    #[TestWith(['Superadmin'])]
    #[TestWith(['Direktur Utama'])]
    #[TestWith(['Direktur'])]
    #[TestWith(['Komisaris'])]
    public function test_non_reviewing_roles_cannot_open_verification_pages(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->get('/verifications')->assertForbidden();
        $this->get('/verifications/1')->assertForbidden();
        $this->get('/tasks')->assertOk()->assertDontSee('href="'.route('verifications.index').'"', false);
    }

    public function test_supervisor_sees_all_branches_but_not_own_tasks_and_only_latest_submissions(): void
    {
        $supervisor = User::factory()->create(['role' => 'Supervisor', 'branch' => 'Jakarta']);
        $manager = User::factory()->create(['role' => 'Kacab', 'branch' => 'Bandung']);
        $this->submission(User::factory()->create(['branch' => 'Jakarta']), $manager, 'Antrean Jakarta');
        $this->submission(User::factory()->create(['branch' => 'Bandung']), $manager, 'Antrean Bandung');
        $own = $this->submission($supervisor, $manager, 'Judul privat supervisor');
        $reviewed = $this->submission(User::factory()->create(['branch' => 'Bandung']), $manager, 'Sudah selesai');
        DB::table('task_submissions')->insert(['assignment_id' => $reviewed->assignment, 'revision' => 2, 'reported_activities' => '[]', 'status' => 'Disetujui', 'created_at' => '2026-11-05 05:00:00']);
        DB::table('task_assignments')->where('id', $reviewed->assignment)->update(['status' => 'Disetujui']);
        $this->actingAs($supervisor)->get('/verifications')->assertOk()->assertSee('Antrean Jakarta')->assertSee('Antrean Bandung')
            ->assertDontSee('Judul privat supervisor')->assertDontSee('Sudah selesai')->assertViewHas('items', fn ($items): bool => $items->total() === 2);
        $this->get('/verifications/'.$own->assignment)->assertNotFound();
        $this->get('/verifications?status=Disetujui')->assertOk()->assertSee('Sudah selesai')->assertSee('Revisi 2')
            ->assertViewHas('items', fn ($items): bool => $items->total() === 1);
    }

    public function test_kacab_only_sees_assignments_they_review_and_search_cannot_escape_scope(): void
    {
        $manager = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $other = User::factory()->create(['role' => 'Kacab', 'branch' => 'Bandung']);
        $own = $this->submission(User::factory()->create(['branch' => 'Jakarta']), $manager, 'Bukti Jakarta');
        $foreign = $this->submission(User::factory()->create(['branch' => 'Bandung']), $other, 'Rahasia Bandung');
        $this->actingAs($manager)->get('/verifications')->assertOk()->assertSee('Bukti Jakarta')->assertDontSee('Rahasia Bandung');
        $this->get('/verifications?q=Rahasia&status=semua')->assertOk()->assertViewHas('items', fn ($items): bool => $items->total() === 0);
        $this->get('/verifications/'.$foreign->assignment)->assertNotFound();
        $this->get('/verifications/'.$own->assignment)->assertOk()->assertSee('Simpan hasil pemeriksaan');
    }

    public function test_review_form_is_separate_from_task_page_and_decision_updates_queue(): void
    {
        $manager = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $staff = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $row = $this->submission($staff, $manager, '<script>alert(1)</script>');
        $file = DB::table('task_evidence')->insertGetId(['submission_id' => $row->submission, 'path' => 'evidence/proof.png', 'mime' => 'image/png', 'original_name' => 'proof.png']);
        $this->actingAs($manager)->get('/tasks/'.$row->task)->assertOk()->assertSee('Buka verifikasi bukti')->assertDontSee('Simpan hasil pemeriksaan');
        $this->get('/verifications/'.$row->assignment)->assertOk()->assertSee('Simpan hasil pemeriksaan')->assertSee(route('evidence.show', $file))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post('/submissions/'.$row->submission.'/review', ['status' => 'Disetujui'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('task_assignments', ['id' => $row->assignment, 'status' => 'Disetujui']);
        $this->get('/verifications')->assertOk()->assertViewHas('items', fn ($items): bool => $items->total() === 0);
        $this->get('/verifications?status=Disetujui')->assertOk()->assertViewHas('items', fn ($items): bool => $items->total() === 1);
        $this->get('/verifications/'.$row->assignment)->assertOk()->assertDontSee('Simpan hasil pemeriksaan');
        $this->actingAs($staff)->get('/tasks/'.$row->task)->assertOk()->assertSee('Disetujui');
    }

    public function test_queue_filters_are_validated_and_preserved_when_changing_page(): void
    {
        $supervisor = User::factory()->create(['role' => 'Supervisor']);
        $staff = User::factory()->create(['branch' => 'Jakarta']);
        for ($index = 0; $index < 21; $index++) {
            $this->submission($staff, $supervisor, 'Antrean '.$index);
        }
        $this->actingAs($supervisor)->get('/verifications?q=Antrean&status=semua')->assertOk()
            ->assertViewHas('items', fn ($items): bool => $items->count() === 20 && $items->total() === 21)
            ->assertSee('q=Antrean', false)->assertSee('status=semua', false);
        $this->get('/verifications?q=Antrean&status=semua&page=2')->assertOk()->assertViewHas('items', fn ($items): bool => $items->count() === 1);
        $this->get('/verifications?status=invalid')->assertSessionHasErrors('status');
        $this->get('/verifications?q='.str_repeat('x', 101))->assertSessionHasErrors('q');
    }
}
