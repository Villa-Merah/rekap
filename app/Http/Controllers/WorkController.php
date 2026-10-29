<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WorkAccess;
use App\Services\WorkNotice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkController extends Controller
{
    private const USER_ROLES = ['Staff', 'Marketing', 'Kacab', 'Direktur', 'Superadmin'];

    private function task(int $id): object
    {
        $task = DB::table('work_tasks')->find($id);
        abort_unless($task, 404);
        $task->activities = json_decode($task->activities, true);

        return $task;
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $tasks = DB::table('work_tasks')->when($user->role !== 'Superadmin', function ($query) use ($user) {
            $query->where(function ($query) use ($user) {
                if (WorkAccess::manages($user)) {
                    $query->where('work_tasks.branch', $user->branch);
                }
                $query->orWhereExists(function ($query) use ($user) {
                    $query->selectRaw('1')->from('task_assignments')->whereColumn('task_id', 'work_tasks.id')->where(function ($query) use ($user) {
                        $query->where('user_id', $user->id)->orWhere('reviewer_id', $user->id);
                    });
                });
            });
        })->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.mb_substr($request->string('q')->toString(), 0, 100).'%'));
        $scope = clone $tasks;
        $summary = [
            'total' => (clone $scope)->count(),
            'waiting' => DB::table('task_assignments')->whereIn('task_id', (clone $scope)->select('work_tasks.id'))->where('reviewer_id', $user->id)->where('status', 'Menunggu verifikasi')->count(),
            'unfinished' => DB::table('task_assignments')->whereIn('task_id', (clone $scope)->select('work_tasks.id'))->where('user_id', $user->id)->whereNotIn('status', ['Disetujui', 'Ditolak'])->count(),
        ];
        if ($request->filled('status')) {
            $tasks->whereExists(function ($query) use ($request, $user) {
                $query->selectRaw('1')->from('task_assignments')->whereColumn('task_id', 'work_tasks.id')->where('status', $request->string('status')->toString());
                if (! WorkAccess::manages($user)) {
                    $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('reviewer_id', $user->id));
                }
            });
        }
        $tasks = $tasks->select('work_tasks.*')->selectSub(DB::table('task_assignments')->selectRaw('count(*)')->whereColumn('task_id', 'work_tasks.id'), 'recipients_count')
            ->selectSub(DB::table('task_assignments')->selectRaw('count(*)')->whereColumn('task_id', 'work_tasks.id')->where('status', 'Disetujui'), 'approved_count')->orderByDesc('id')->paginate(12)->withQueryString();

        return view('work.index', compact('tasks', 'summary'));
    }

    public function create(Request $request): View
    {
        abort_unless(WorkAccess::manages($request->user()), 403);
        $members = User::query()->when($request->user()->role !== 'Superadmin', fn ($q) => $q->where('branch', $request->user()->branch))->whereNotNull('branch')->orderBy('branch')->orderBy('name')->get();

        return view('work.create', compact('members'));
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(WorkAccess::manages($actor), 403);
        $data = $request->validate([
            'title' => 'required|string|max:180', 'description' => 'required|string|max:5000',
            'branch' => 'required|string|max:255', 'platform' => ['required', Rule::in(array_keys(config('workflow.platforms')))],
            'target_url' => 'required|url:http,https|max:2000', 'activities' => 'required|array|min:1', 'activities.*' => 'required|string|distinct',
            'recipients' => 'required|array|min:1|max:500', 'recipients.*' => 'required|integer|distinct|exists:users,id',
            'account_count' => 'required|integer|min:1|max:20', 'priority' => ['required', Rule::in(['Normal', 'Tinggi', 'Mendesak'])],
            'comment_instructions' => 'nullable|string|max:3000', 'evidence_instructions' => 'required|string|max:3000',
            'starts_at' => 'required|date_format:Y-m-d\TH:i', 'due_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at',
        ]);
        abort_unless(WorkAccess::manages($actor, $data['branch']), 403);
        if (array_diff($data['activities'], config('workflow.platforms')[$data['platform']])) {
            throw ValidationException::withMessages(['activities' => 'Aktivitas tidak tersedia untuk platform yang dipilih.']);
        }
        $members = User::whereIn('id', $data['recipients'])->get();
        if ($members->contains(fn ($member) => $member->branch !== $data['branch'])) {
            throw ValidationException::withMessages(['recipients' => 'Semua penerima harus berasal dari cabang tugas.']);
        }
        $reviewers = [];
        foreach ($members as $member) {
            $reviewer = $member->id === $actor->id ? $member->supervisor_id : $actor->id;
            if (! $reviewer || $reviewer === $member->id) {
                throw ValidationException::withMessages(['recipients' => 'Tugas pribadi memerlukan atasan pemeriksa. Minta Superadmin menetapkan atasan di halaman akun dan kuota.']);
            }
            $reviewers[$member->id] = $reviewer;
        }
        $id = DB::transaction(function () use ($actor, $data, $reviewers) {
            $id = DB::table('work_tasks')->insertGetId([
                ...collect($data)->except(['recipients', 'starts_at', 'due_at', 'activities'])->all(),
                'creator_id' => $actor->id, 'activities' => json_encode($data['activities']),
                'starts_at' => CarbonImmutable::parse($data['starts_at'], config('workflow.timezone'))->utc(),
                'due_at' => CarbonImmutable::parse($data['due_at'], config('workflow.timezone'))->utc(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($reviewers as $recipientId => $reviewerId) {
                DB::table('task_assignments')->insert(['task_id' => $id, 'user_id' => $recipientId, 'reviewer_id' => $reviewerId, 'created_at' => now(), 'updated_at' => now()]);
                WorkNotice::send($recipientId, 'Tugas baru: '.$data['title'], 'Anda menerima tugas '.$data['platform'].'. Periksa instruksi dan tenggat.', route('tasks.show', $id, false), 'task-created:'.$id);
            }
            WorkNotice::audit($actor->id, 'Tugas dibuat', 'task', $id, ['recipients' => array_keys($reviewers)]);

            return $id;
        });

        return redirect()->route('tasks.show', $id)->with('success', 'Tugas dibagikan dan notifikasi telah dikirim.');
    }

    public function show(Request $request, int $task): View
    {
        $task = $this->task($task);
        abort_unless(WorkAccess::canView($request->user(), $task), 403);
        $assignments = DB::table('task_assignments')->join('users', 'users.id', '=', 'task_assignments.user_id')->where('task_id', $task->id)
            ->when(! WorkAccess::manages($request->user(), $task->branch), fn ($q) => $q->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('reviewer_id', $request->user()->id)))
            ->select('task_assignments.*', 'users.name')->get();
        $submissions = DB::table('task_submissions')->whereIn('assignment_id', $assignments->pluck('id'))->orderByDesc('id')->get();
        $evidence = DB::table('task_evidence')->whereIn('submission_id', $submissions->pluck('id'))->get()->groupBy('submission_id');
        $accounts = DB::table('social_accounts')->where('user_id', $request->user()->id)->where('platform', $task->platform)->get();

        return view('work.show', compact('task', 'assignments', 'submissions', 'evidence', 'accounts'));
    }

    public function start(Request $request, int $assignment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assignment) {
            $row = DB::table('task_assignments')->lockForUpdate()->find($assignment);
            abort_unless($row && $row->user_id === $request->user()->id, 403);
            $task = $this->task($row->task_id);
            abort_unless($task->status === 'Aktif' && CarbonImmutable::parse($task->starts_at, 'UTC')->isPast(), 422, 'Tugas belum dimulai.');
            if ($row->status === 'Belum dikerjakan') {
                DB::table('task_assignments')->where('id', $assignment)->update(['status' => 'Dalam proses', 'updated_at' => now()]);
                WorkNotice::audit($request->user()->id, 'Tugas dimulai', 'assignment', $assignment);
            }
        });

        return back()->with('success', 'Status pengerjaan diperbarui.');
    }

    public function submit(Request $request, int $assignment): RedirectResponse
    {
        $row = DB::table('task_assignments')->find($assignment);
        abort_unless($row && $row->user_id === $request->user()->id, 403);
        $task = $this->task($row->task_id);
        $data = $request->validate([
            'accounts' => 'required|array|size:'.$task->account_count, 'accounts.*' => 'required|integer|distinct',
            'activities' => 'required|array', 'activities.*' => 'required|string|distinct',
            'notes' => 'nullable|string|max:3000', 'performed_at' => 'required|date_format:Y-m-d\TH:i',
            'evidence_confirmation' => 'accepted',
            'evidence' => 'required|array|min:1|max:'.config('workflow.evidence_max_files'),
            'evidence.*' => 'required|file|image|mimes:'.implode(',', config('workflow.evidence_types')).'|max:'.config('workflow.evidence_max_kb'),
        ]);
        if (array_diff($task->activities, $data['activities']) || array_diff($data['activities'], $task->activities)) {
            throw ValidationException::withMessages(['activities' => 'Konfirmasikan seluruh aktivitas yang diwajibkan pada tugas.']);
        }
        $performedAt = CarbonImmutable::parse($data['performed_at'], config('workflow.timezone'))->utc();
        if ($performedAt->isFuture() || $performedAt->lt(CarbonImmutable::parse($task->starts_at, 'UTC'))) {
            throw ValidationException::withMessages(['performed_at' => 'Waktu pelaksanaan harus sesudah tugas dimulai dan tidak boleh di masa depan.']);
        }
        $accounts = DB::table('social_accounts')->whereIn('id', $data['accounts'])->where('user_id', $request->user()->id)->where('platform', $task->platform)->get();
        if ($accounts->count() !== (int) $task->account_count) {
            throw ValidationException::withMessages(['accounts' => 'Gunakan akun milik sendiri sesuai platform dan jumlah yang ditugaskan.']);
        }
        $stored = [];
        try {
            DB::transaction(function () use ($request, $assignment, $task, $data, $performedAt, $accounts, &$stored) {
                $row = DB::table('task_assignments')->lockForUpdate()->find($assignment);
                if (! in_array($row->status, ['Belum dikerjakan', 'Dalam proses', 'Perlu perbaikan']) || $task->status !== 'Aktif' || CarbonImmutable::parse($task->starts_at, 'UTC')->isFuture()) {
                    throw ValidationException::withMessages(['evidence' => 'Status pengerjaan tidak mengizinkan pengiriman bukti.']);
                }
                $revision = DB::table('task_submissions')->where('assignment_id', $assignment)->max('revision') + 1;
                $id = DB::table('task_submissions')->insertGetId(['assignment_id' => $assignment, 'revision' => $revision, 'reported_activities' => '[]', 'notes' => $data['notes'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
                $evidenceIds = [];
                foreach ($request->file('evidence') as $file) {
                    $path = $file->store('evidence', 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Penyimpanan bukti gagal.');
                    }
                    $stored[] = $path;
                    $evidenceIds[] = DB::table('task_evidence')->insertGetId(['submission_id' => $id, 'path' => $path, 'mime' => $file->getMimeType(), 'original_name' => mb_substr($file->getClientOriginalName(), 0, 240), 'created_at' => now(), 'updated_at' => now()]);
                }
                $activities = [];
                foreach ($accounts as $account) {
                    foreach ($task->activities as $activity) {
                        $activities[] = ['account_id' => $account->id, 'account_name' => $account->name, 'identity' => $account->identity, 'activity' => $activity, 'performed_at' => $performedAt->toIso8601String(), 'evidence_ids' => $evidenceIds];
                    }
                }
                DB::table('task_submissions')->where('id', $id)->update(['reported_activities' => json_encode($activities)]);
                DB::table('task_assignments')->where('id', $assignment)->update(['status' => 'Menunggu verifikasi', 'updated_at' => now()]);
                WorkNotice::send($row->reviewer_id, 'Bukti menunggu verifikasi', $request->user()->name.' mengirim bukti untuk '.$task->title.'.', route('tasks.show', $task->id, false), 'submission:'.$id);
                WorkNotice::audit($request->user()->id, 'Bukti dikirim', 'submission', $id, ['revision' => $revision]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored);
            throw $error;
        }

        return back()->with('success', 'Bukti dikirim untuk pemeriksaan manual.');
    }

    public function review(Request $request, int $submission): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['Disetujui', 'Perlu perbaikan', 'Ditolak'])], 'review_notes' => 'nullable|required_unless:status,Disetujui|string|max:3000']);
        DB::transaction(function () use ($request, $submission, $data) {
            $snapshot = DB::table('task_submissions')->find($submission);
            abort_unless($snapshot, 404);
            $assignment = DB::table('task_assignments')->lockForUpdate()->find($snapshot->assignment_id);
            $actor = $request->user();
            abort_unless($actor->id !== $assignment->user_id && ($actor->id === $assignment->reviewer_id || $actor->role === 'Superadmin'), 403);
            $latest = DB::table('task_submissions')->where('assignment_id', $assignment->id)->max('id');
            if ($latest !== $snapshot->id || $assignment->status !== 'Menunggu verifikasi' || $snapshot->status !== 'Menunggu verifikasi') {
                throw ValidationException::withMessages(['status' => 'Bukti ini sudah diperiksa atau telah digantikan revisi terbaru.']);
            }
            DB::table('task_submissions')->where('id', $submission)->update(['status' => $data['status'], 'reviewer_id' => $actor->id, 'review_notes' => $data['review_notes'] ?? null, 'reviewed_at' => now(), 'updated_at' => now()]);
            DB::table('task_assignments')->where('id', $assignment->id)->update(['status' => $data['status'], 'updated_at' => now()]);
            $task = $this->task($assignment->task_id);
            WorkNotice::send($assignment->user_id, 'Hasil pemeriksaan: '.$data['status'], $task->title.'. '.($data['review_notes'] ?? 'Bukti telah diperiksa secara manual.'), route('tasks.show', $task->id, false), 'review:'.$submission);
            WorkNotice::audit($actor->id, 'Bukti diperiksa', 'submission', $submission, $data);
        });

        return back()->with('success', 'Hasil pemeriksaan disimpan dan pengguna telah diberi notifikasi.');
    }

    public function evidence(Request $request, int $evidence): BinaryFileResponse
    {
        $file = DB::table('task_evidence')->find($evidence);
        abort_unless($file, 404);
        $submission = DB::table('task_submissions')->find($file->submission_id);
        $assignment = DB::table('task_assignments')->find($submission->assignment_id);
        $task = $this->task($assignment->task_id);
        $actor = $request->user();
        abort_unless(in_array($actor->id, [$assignment->user_id, $assignment->reviewer_id]) || WorkAccess::manages($actor, $task->branch), 403);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return response()->file(Storage::disk('local')->path($file->path), ['Content-Type' => $file->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function accounts(Request $request): View
    {
        $actor = $request->user();
        $members = User::query()->when($actor->role !== 'Superadmin', fn ($q) => WorkAccess::manages($actor) ? $q->where('branch', $actor->branch) : $q->where('id', $actor->id))->orderBy('name')->get();
        $accounts = DB::table('social_accounts')->join('users', 'users.id', '=', 'social_accounts.user_id')->whereIn('user_id', $members->pluck('id'))->select('social_accounts.*', 'users.name as owner_name')->orderByDesc('social_accounts.id')->paginate(20);
        $counts = DB::table('social_accounts')->whereIn('user_id', $members->pluck('id'))->selectRaw('user_id, platform, count(*) as total')->groupBy('user_id', 'platform')->get()->keyBy(fn ($row) => $row->user_id.':'.$row->platform);
        $quotas = DB::table('account_quotas')->whereIn('user_id', $members->pluck('id'))->get()->keyBy(fn ($row) => $row->user_id.':'.$row->platform);
        $supervisors = $actor->role === 'Superadmin' ? User::orderBy('name')->get() : collect();

        return view('work.accounts', compact('accounts', 'members', 'counts', 'quotas', 'supervisors'));
    }

    public function createUser(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor->role === 'Superadmin' || WorkAccess::manages($actor), 403);
        $manageableRoles = $actor->role === 'Superadmin' ? self::USER_ROLES : ['Staff', 'Marketing'];

        return view('work.users.create', compact('manageableRoles'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->role === 'Superadmin' || WorkAccess::manages($actor), 403);

        $manageableRoles = $actor->role === 'Superadmin' ? self::USER_ROLES : ['Staff', 'Marketing'];
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in($manageableRoles)],
            'branch' => 'nullable|required_unless:role,Superadmin|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $branch = $actor->role === 'Superadmin' ? ($data['branch'] ?: null) : $actor->branch;

        DB::transaction(function () use ($actor, $data, $branch): void {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'must_change_password' => true,
                'role' => $data['role'],
                'branch' => $branch,
            ])->save();

            if ($data['role'] === 'Staff') {
                $timestamp = now();
                $targets = collect(config('workflow.account_platforms'))
                    ->map(fn (string $platform): array => [
                        'user_id' => $user->id,
                        'platform' => $platform,
                        'required_count' => config('workflow.account_target'),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])->all();

                DB::table('account_quotas')->upsert(
                    $targets,
                    ['user_id', 'platform'],
                    ['required_count', 'updated_at'],
                );
            }

            WorkNotice::audit($actor->id, 'Pengguna ditambahkan', 'user', (int) $user->id, [
                'role' => $data['role'],
                'branch' => $branch,
            ]);
        });

        return back()->with('success', 'Pengguna berhasil ditambahkan. Sampaikan kata sandi awal melalui jalur yang aman.');
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(config('workflow.account_platforms'))], 'name' => 'required|string|max:180',
            'identity' => ['required', 'string', 'max:180', Rule::unique('social_accounts')->where(fn ($q) => $q->where('user_id', $request->user()->id)->where('platform', $request->input('platform')))],
            'profile_url' => 'nullable|url:http,https|max:2000',
        ]);
        DB::transaction(function () use ($request, $data) {
            $id = DB::table('social_accounts')->insertGetId([...$data, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            WorkNotice::audit($request->user()->id, 'Akun didaftarkan', 'account', $id);
        });

        return back()->with('success', 'Akun berhasil didaftarkan.');
    }

    public function quota(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'platform' => ['required', Rule::in(config('workflow.account_platforms'))], 'required_count' => 'required|integer|min:0|max:100']);
        $member = User::findOrFail($data['user_id']);
        abort_unless($request->user()->role === 'Superadmin' || ($member->branch !== null && WorkAccess::manages($request->user(), $member->branch)), 403);
        DB::transaction(function () use ($request, $data) {
            DB::table('account_quotas')->upsert([[...$data, 'created_at' => now(), 'updated_at' => now()]], ['user_id', 'platform'], ['required_count', 'updated_at']);
            WorkNotice::audit($request->user()->id, 'Kuota akun diperbarui', 'user', (int) $data['user_id'], $data);
        });

        return back()->with('success', 'Kuota akun disimpan.');
    }

    public function supervisor(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'Superadmin', 403);
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'supervisor_id' => 'required|integer|different:user_id|exists:users,id']);
        DB::transaction(function () use ($request, $data) {
            User::where('id', $data['user_id'])->update(['supervisor_id' => $data['supervisor_id']]);
            WorkNotice::audit($request->user()->id, 'Atasan pemeriksa ditetapkan', 'user', (int) $data['user_id'], $data);
        });

        return back()->with('success', 'Atasan untuk tugas pribadi berikutnya berhasil ditetapkan.');
    }
}
