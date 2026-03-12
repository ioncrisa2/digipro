<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_valuation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guideline_set_id')->constrained('ref_guideline_sets');
            $table->smallInteger('year');
            $table->string('key');
            $table->string('label');
            $table->decimal('value_number', 12, 4)->nullable();
            $table->string('value_text')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['guideline_set_id', 'year', 'key'], 'uq_ref_valuation_setting_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_valuation_settings');
    }
};
