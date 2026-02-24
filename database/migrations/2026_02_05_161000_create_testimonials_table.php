<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('quote');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('testimonials')->insert([
            [
                'name' => 'Budi Santoso',
                'role' => 'Homeowner',
                'quote' => 'The process was incredibly professional. The digital report is detailed yet easy to understand.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Citra Lestari',
                'role' => 'Real Estate Agent',
                'quote' => 'HJAR Valuer is my go-to for quick client estimates. Their platform saves me days of paperwork.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PT. Maju Bersama',
                'role' => 'Corporate Client',
                'quote' => 'We use their multi-asset valuation for our portfolio. Consistent, accurate, and efficient.',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};