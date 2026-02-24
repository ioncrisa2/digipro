<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('building_cost_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('building_valuation_id')
                ->constrained('building_valuations')
                ->cascadeOnDelete();

            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('ref_cost_elements');

            $table->string('element_code')->nullable();
            $table->string('element_name');

            $table->string('unit')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();

            $table->bigInteger('ref_unit_cost')->nullable();
            $table->decimal('ikk_value_used', 8, 4)->nullable();
            $table->bigInteger('adjusted_unit_cost')->nullable();

            $table->bigInteger('line_total')->nullable();
            $table->json('meta_json')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_cost_items');
    }
};
