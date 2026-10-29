<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $platforms = ['Google/Gmail', 'YouTube', 'Instagram', 'Facebook', 'TikTok'];

        DB::table('users')->where('role', 'Staff')->orderBy('id')->chunkById(100, function ($staffMembers) use ($platforms): void {
            $timestamp = now();
            $targets = [];

            foreach ($staffMembers as $staffMember) {
                foreach ($platforms as $platform) {
                    $targets[] = [
                        'user_id' => $staffMember->id,
                        'platform' => $platform,
                        'required_count' => 10,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            DB::table('account_quotas')->upsert(
                $targets,
                ['user_id', 'platform'],
                ['required_count', 'updated_at'],
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Staff account targets overwrite existing quota values and cannot be reversed safely.');
    }
};
