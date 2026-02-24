<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_categories', function (Blueprint $table) {
            $table->boolean('show_in_nav')
                ->default(false)
                ->after('is_active')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('article_categories', function (Blueprint $table) {
            $table->dropIndex(['show_in_nav']);
            $table->dropColumn('show_in_nav');
        });
    }
};
