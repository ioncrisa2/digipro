<?php

namespace Database\Seeders;

use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Database\Seeder;

class AdminWorkspaceAccessSeeder extends Seeder
{
    public function run(): void
    {
        AdminWorkspaceAccessSynchronizer::sync();
    }
}
