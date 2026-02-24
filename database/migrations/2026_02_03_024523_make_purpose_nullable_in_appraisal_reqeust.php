<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // MySQL/MariaDB: make ENUM nullable via raw SQL.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE appraisal_requests MODIFY purpose ENUM('jual_beli','penjaminan','lelang') NULL");
            return;
        }

    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE appraisal_requests MODIFY purpose ENUM('jual_beli','penjaminan','lelang') NOT NULL");
            return;
        }
    }
};
