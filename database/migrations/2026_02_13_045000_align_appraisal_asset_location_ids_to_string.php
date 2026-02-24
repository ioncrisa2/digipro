<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE appraisal_assets SET province_id = LPAD(province_id, 2, '0') WHERE province_id IS NOT NULL");
            DB::statement("UPDATE appraisal_assets SET regency_id = LPAD(regency_id, 4, '0') WHERE regency_id IS NOT NULL");
            DB::statement("UPDATE appraisal_assets SET district_id = LPAD(district_id, 7, '0') WHERE district_id IS NOT NULL");
            DB::statement("UPDATE appraisal_assets SET village_id = LPAD(village_id, 10, '0') WHERE village_id IS NOT NULL");

            DB::statement("ALTER TABLE appraisal_assets MODIFY province_id VARCHAR(2) NULL");
            DB::statement("ALTER TABLE appraisal_assets MODIFY regency_id VARCHAR(4) NULL");
            DB::statement("ALTER TABLE appraisal_assets MODIFY district_id VARCHAR(7) NULL");
            DB::statement("ALTER TABLE appraisal_assets MODIFY village_id VARCHAR(10) NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("UPDATE appraisal_assets SET province_id = LPAD(CAST(province_id AS TEXT), 2, '0') WHERE province_id IS NOT NULL");
            DB::statement("UPDATE appraisal_assets SET regency_id = LPAD(CAST(regency_id AS TEXT), 4, '0') WHERE regency_id IS NOT NULL");
            DB::statement("UPDATE appraisal_assets SET district_id = LPAD(CAST(district_id AS TEXT), 7, '0') WHERE district_id IS NOT NULL");
            DB::statement("UPDATE appraisal_assets SET village_id = LPAD(CAST(village_id AS TEXT), 10, '0') WHERE village_id IS NOT NULL");

            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN province_id TYPE VARCHAR(2) USING province_id::VARCHAR");
            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN regency_id TYPE VARCHAR(4) USING regency_id::VARCHAR");
            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN district_id TYPE VARCHAR(7) USING district_id::VARCHAR");
            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN village_id TYPE VARCHAR(10) USING village_id::VARCHAR");

            return;
        }

        // Unsupported driver: skip safely.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE appraisal_assets MODIFY province_id BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE appraisal_assets MODIFY regency_id BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE appraisal_assets MODIFY district_id BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE appraisal_assets MODIFY village_id BIGINT UNSIGNED NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN province_id TYPE BIGINT USING NULLIF(province_id, '')::BIGINT");
            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN regency_id TYPE BIGINT USING NULLIF(regency_id, '')::BIGINT");
            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN district_id TYPE BIGINT USING NULLIF(district_id, '')::BIGINT");
            DB::statement("ALTER TABLE appraisal_assets ALTER COLUMN village_id TYPE BIGINT USING NULLIF(village_id, '')::BIGINT");
            return;
        }

        // Unsupported driver: skip safely.
    }
};
