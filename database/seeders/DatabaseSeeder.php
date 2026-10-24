<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = (string) config('app.admin_email', 'admin@wifi.local');
        $admin = User::where('email', $email)->first();

        if ($admin) {
            // Never reset an existing administrator's password during seeding.
            if (! $admin->is_admin) {
                $admin->forceFill(['is_admin' => true])->save();
            }
        } else {
            $password = (string) config('app.admin_password', '');

            if ($password === '') {
                $password = Str::password(20);
                $this->command?->warn("No ADMIN_PASSWORD set; generated admin password: {$password}");
            }

            $admin = User::create([
                'name' => 'Admin',
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $admin->forceFill(['is_admin' => true])->save();
        }

        $this->call(PlansSeeder::class);
    }
}
