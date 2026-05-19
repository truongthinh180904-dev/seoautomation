<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::create([
            'name' => 'Default Tenant',
            'slug' => 'default',
        ]);

        User::factory()->create([
            'name'      => 'Admin User',
            'email'     => 'admin@example.com',
            'password'  => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
        ]);

        $this->call([
            SubscriptionPlanSeeder::class,
            AIPromptSeeder::class,
        ]);
    }
}
