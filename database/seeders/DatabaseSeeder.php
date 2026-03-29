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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            AdminWorkspaceAccessSeeder::class,
            SuperAdminUserSeeder::class,
            ArticleCategorySeeder::class,
            TagSeeder::class,
            RefUsageToMappiGroupSeeder::class,
        ]);

        if (env('BTB_WORKBOOK_2025_PATH') && env('BTB_GUIDELINE_SET_ID')) {
            $this->call([
                BtbWorkbook2025ReferenceSeeder::class,
            ]);
        }
    }
}
