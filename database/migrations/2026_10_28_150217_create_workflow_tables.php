<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('name');
            $table->string('identity');
            $table->text('profile_url')->nullable();
            $table->string('status')->default('Terdaftar');
            $table->timestamps();
            $table->unique(['user_id', 'platform', 'identity']);
        });
        Schema::create('account_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->unsignedInteger('required_count');
            $table->timestamps();
            $table->unique(['user_id', 'platform']);
        });
        Schema::create('work_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('branch')->index();
            $table->string('title');
            $table->text('description');
            $table->string('platform', 30);
            $table->text('target_url');
            $table->json('activities');
            $table->unsignedInteger('account_count')->default(1);
            $table->text('comment_instructions')->nullable();
            $table->text('evidence_instructions');
            $table->string('priority');
            $table->string('status')->default('Aktif');
            $table->timestamp('starts_at');
            $table->timestamp('due_at')->index();
            $table->timestamps();
        });
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('Belum dikerjakan')->index();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });
        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('task_assignments')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('reported_activities');
            $table->text('notes')->nullable();
            $table->string('status')->default('Menunggu verifikasi');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'revision']);
        });
        Schema::create('task_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('task_submissions')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 40);
            $table->string('original_name');
            $table->timestamps();
        });
        Schema::create('work_notice_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->unique(['user_id', 'event_key']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('details');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['audit_logs', 'work_notice_keys', 'task_evidence', 'task_submissions', 'task_assignments', 'work_tasks', 'account_quotas', 'social_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }
};
