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
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();

            // data form
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');

            // untuk pengelolaan di sistem (ticket sederhana)
            $table->string('status')->default('new'); // new, in_progress, done, archived
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // metadata (opsional tapi berguna)
            $table->string('source')->nullable();           // contoh: 'landing-contact'
            $table->string('ip_address', 45)->nullable();   // IPv4/IPv6

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
