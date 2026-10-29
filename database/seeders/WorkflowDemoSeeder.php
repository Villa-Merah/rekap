<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WorkNotice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Data demo hanya untuk lingkungan lokal.');
        }
        $this->call(CommunicationDemoSeeder::class);
        $manager = User::where('email', 'nadia@example.test')->firstOrFail();
        $members = User::whereIn('email', ['arif@example.test', 'sinta@example.test'])->get();
        DB::transaction(function () use ($manager, $members) {
            foreach ($members as $member) {
                DB::table('social_accounts')->insertOrIgnore(['user_id' => $member->id, 'platform' => 'Instagram', 'name' => $member->name.' (Demo)', 'identity' => 'demo_rekap_'.$member->id, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('account_quotas')->insertOrIgnore(['user_id' => $member->id, 'platform' => 'Instagram', 'required_count' => 2, 'created_at' => now(), 'updated_at' => now()]);
            }
            if (DB::table('work_tasks')->where('title', 'Demo · Koordinasi publikasi cabang')->where('creator_id', $manager->id)->exists()) {
                return;
            }
            $id = DB::table('work_tasks')->insertGetId(['creator_id' => $manager->id, 'branch' => 'Jakarta', 'title' => 'Demo · Koordinasi publikasi cabang', 'description' => 'DATA SINTETIS untuk mencoba penugasan dan verifikasi. URL berikut merupakan contoh, bukan postingan aktif. Gunakan tugas baru dengan postingan organisasi yang sesuai untuk aktivitas sebenarnya.', 'platform' => 'Instagram', 'target_url' => 'https://www.instagram.com/p/DEMO_REKAP_BUZZER/', 'activities' => json_encode(['like', 'komentar']), 'account_count' => 1, 'comment_instructions' => 'Gunakan komentar yang relevan sesuai arahan organisasi.', 'evidence_instructions' => 'Untuk pengujian lokal, unggah gambar uji. Pemeriksa harus meninjau bukti secara manual.', 'priority' => 'Normal', 'starts_at' => now()->subHour(), 'due_at' => now()->addDays(2), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($members as $member) {
                DB::table('task_assignments')->insert(['task_id' => $id, 'user_id' => $member->id, 'reviewer_id' => $manager->id, 'created_at' => now(), 'updated_at' => now()]);
                WorkNotice::send($member->id, 'Tugas demo tersedia', 'Coba alur pengiriman bukti dan pemeriksaan pada tugas sintetis.', route('tasks.show', $id, false), 'task-created:'.$id);
            }
            WorkNotice::audit($manager->id, 'Tugas demo dibuat', 'task', $id);
        });
    }
}
