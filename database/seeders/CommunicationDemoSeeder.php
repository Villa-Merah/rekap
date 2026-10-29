<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CommunicationDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Data demo hanya untuk lingkungan lokal.');
        }
        foreach ([
            ['Nadia Putri', 'nadia@example.test', 'Kacab', 'Jakarta'],
            ['Arif Pratama', 'arif@example.test', 'Staff', 'Jakarta'],
            ['Sinta Maharani', 'sinta@example.test', 'Marketing', 'Jakarta'],
            ['Dimas Saputra', 'dimas@example.test', 'Staff', 'Bandung'],
            ['Admin Demo', 'admin@example.test', 'Superadmin', null],
        ] as [$name, $email, $role, $branch]) {
            $user = User::firstOrNew(['email' => $email]);
            if (! $user->exists) {
                $user->forceFill(['name' => $name, 'password' => 'DemoBuzzer!2026', 'role' => $role, 'branch' => $branch])->save();
            }
        }
    }
}
