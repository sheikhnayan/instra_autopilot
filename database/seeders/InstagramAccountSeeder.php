<?php

namespace Database\Seeders;

use App\Models\InstagramAccount;
use Illuminate\Database\Seeder;

class InstagramAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'username' => 'kentucky2030',
                'display_name' => 'Kentucky 2030',
                'avatar_color' => '#8B5CF6',
                'is_active' => true
            ],
            [
                'username' => 'albany2030',
                'display_name' => 'Albany 2030',
                'avatar_color' => '#EC4899',
                'is_active' => true
            ],
            [
                'username' => 'alabama2030',
                'display_name' => 'Alabama 2030',
                'avatar_color' => '#F59E0B',
                'is_active' => true
            ],
            [
                'username' => 'tampa2030',
                'display_name' => 'Tampa 2030',
                'avatar_color' => '#10B981',
                'is_active' => true
            ],
            [
                'username' => 'florida2030',
                'display_name' => 'Florida 2030',
                'avatar_color' => '#3B82F6',
                'is_active' => true
            ],
            [
                'username' => 'olemiss2030',
                'display_name' => 'Ole Miss 2030',
                'avatar_color' => '#EF4444',
                'is_active' => true
            ]
        ];

        foreach ($accounts as $account) {
            InstagramAccount::create($account);
        }
    }
}
