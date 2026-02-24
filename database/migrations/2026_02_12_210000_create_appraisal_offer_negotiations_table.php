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
        Schema::create('appraisal_offer_negotiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_request_id')
                ->constrained('appraisal_requests')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 40);
            $table->unsignedTinyInteger('round')->nullable();
            $table->unsignedBigInteger('offered_fee')->nullable();
            $table->unsignedBigInteger('expected_fee')->nullable();
            $table->unsignedBigInteger('selected_fee')->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['appraisal_request_id', 'action'], 'aon_request_action_idx');
            $table->index(['appraisal_request_id', 'round'], 'aon_request_round_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_offer_negotiations');
    }
};
