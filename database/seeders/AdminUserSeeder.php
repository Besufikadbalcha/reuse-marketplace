<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('marketplace.admin_email');

        if (! is_string($email) || trim($email) === '') {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new RuntimeException("No user exists for MARKETPLACE_ADMIN_EMAIL [{$email}].");
        }

        if (! $user->isAdmin()) {
            $user->forceFill(['is_admin' => true])->save();
        }
    }
}
