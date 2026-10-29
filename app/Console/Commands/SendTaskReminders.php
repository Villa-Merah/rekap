<?php

namespace App\Console\Commands;

use App\Services\WorkNotice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tasks:remind')]
#[Description('Kirim pengingat tenggat tugas tanpa notifikasi duplikat')]
class SendTaskReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DB::table('task_assignments as a')->join('work_tasks as t', 't.id', '=', 'a.task_id')
            ->where('t.status', 'Aktif')->whereNotIn('a.status', ['Disetujui', 'Ditolak'])
            ->where('t.starts_at', '<=', now())->where('t.due_at', '<=', now()->addDay())
            ->select('a.id', 'a.user_id', 'a.task_id', 't.title', 't.due_at')->orderBy('a.id')
            ->chunkById(200, function ($assignments) {
                foreach ($assignments as $assignment) {
                    $late = CarbonImmutable::parse($assignment->due_at, 'UTC')->isPast();
                    WorkNotice::send($assignment->user_id, $late ? 'Tugas melewati tenggat' : 'Tenggat tugas mendekat', $assignment->title.' · Tenggat '.CarbonImmutable::parse($assignment->due_at, 'UTC')->setTimezone(config('workflow.timezone'))->format('d M Y, H:i'), route('tasks.show', $assignment->task_id, false), 'reminder:'.$assignment->id.':'.($late ? 'late' : '24h'));
                }
            }, 'a.id', 'id');
        $this->info('Pengingat tenggat telah diproses.');

        return self::SUCCESS;
    }
}
