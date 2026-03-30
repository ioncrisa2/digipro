<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index(['is_active', 'sort_order']);
        });

        $rows = DB::table('tags')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id']);

        foreach ($rows as $index => $row) {
            DB::table('tags')
                ->where('id', $row->id)
                ->update(['sort_order' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
