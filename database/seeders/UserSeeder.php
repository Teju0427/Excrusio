<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo accounts (synthetic). The shared demo password is only for the public demo
 * and is set from DEMO_PASSWORD in .env so it is never hard-coded in production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make(config('excursio.demo_password'));

        $users = [
            ['Aarav Demo Admin', 'admin@excursio.test', UserRole::Admin],
            ['Alpine & Tuscan Stays (Demo)', 'host.alpine@excursio.test', UserRole::Provider],
            ['Kansai & Coorg Collective (Demo)', 'host.kansai@excursio.test', UserRole::Provider],
            ['Island Coast Co. (Demo)', 'host.island@excursio.test', UserRole::Provider],
            ['Diya Demo Guest', 'guest@excursio.test', UserRole::Customer],
            ['Kenji Demo Traveller', 'kenji@excursio.test', UserRole::Customer],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = $password;
            $user->email_verified_at = now();
            $user->forceFill(['role' => $role, 'status' => UserStatus::Active, 'is_demo' => true])->save();
        }
    }
}
