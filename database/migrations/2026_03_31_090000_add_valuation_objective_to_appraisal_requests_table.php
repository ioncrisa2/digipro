<?php

use App\Enums\ValuationObjectiveEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table): void {
            $table->string('valuation_objective')
                ->default(ValuationObjectiveEnum::KajianNilaiPasarRange->value)
                ->after('purpose');
        });

        DB::table('appraisal_requests')
            ->whereNull('valuation_objective')
            ->update([
                'valuation_objective' => ValuationObjectiveEnum::KajianNilaiPasarRange->value,
            ]);
    }

    public function down(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table): void {
            $table->dropColumn('valuation_objective');
        });
    }
};
