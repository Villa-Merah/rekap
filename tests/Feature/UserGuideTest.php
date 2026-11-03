<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_sign_in_before_reading_guide(): void
    {
        $this->get('/guide')->assertRedirect('/login');
    }

    #[TestWith(['Staff'])]
    #[TestWith(['Marketing'])]
    #[TestWith(['Direktur'])]
    public function test_members_see_workflow_help_without_management_actions(string $role): void
    {
        $member = User::factory()->create(['role' => $role, 'branch' => 'Jakarta']);
        config(['workflow.evidence_max_files' => 4, 'workflow.evidence_max_kb' => 2048]);

        $this->actingAs($member)->get('/guide')->assertOk()->assertViewIs('work.guide')
            ->assertSee('Panduan penggunaan')->assertSee('Kirim hasil & screenshot bukti', false)
            ->assertSee('maksimal 4 gambar')->assertSee('maksimal 2 MB')
            ->assertSee('Buat & unduh laporan PDF', false)
            ->assertSee(route('accounts.index'))->assertSee(route('reports.index'))
            ->assertDontSee('Panduan Kacab & Superadmin', false)
            ->assertDontSee('href="'.route('users.create').'"', false)
            ->assertDontSee('href="'.route('tasks.create').'"', false);
    }

    #[TestWith(['Kacab'])]
    #[TestWith(['Superadmin'])]
    public function test_managers_see_management_instructions_and_authorized_shortcuts(string $role): void
    {
        $member = User::factory()->create(['role' => $role, 'branch' => 'Jakarta']);

        $response = $this->actingAs($member)->get('/guide')->assertOk()
            ->assertSee('Panduan Kacab & Superadmin', false)
            ->assertSee('href="'.route('tasks.create').'"', false)
            ->assertSee('href="'.route('users.create').'"', false);
        if ($role === 'Superadmin') {
            $response->assertSee('Menetapkan atasan pemeriksa tugas pribadi');
        } else {
            $response->assertDontSee('Menetapkan atasan pemeriksa tugas pribadi');
        }
    }

    public function test_first_login_requires_password_change_before_guide_access(): void
    {
        $member = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($member)->get('/guide')->assertRedirect(route('password.change'));
    }

    public function test_guide_is_linked_from_work_and_communication_navigation(): void
    {
        $member = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);

        $this->actingAs($member)->get('/tasks')->assertOk()
            ->assertSee('href="'.route('guide').'"', false);
        $this->get('/')->assertOk()->assertSee('href="'.route('guide').'"', false);
    }
}
