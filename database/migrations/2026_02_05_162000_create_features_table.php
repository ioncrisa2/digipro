<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('icon')->nullable();
            $table->string('title');
            $table->text('description');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('features')->insert([
            [
                'icon' => 'TrendingUp',
                'title' => 'Accurate Valuations',
                'description' => 'Certified KJPP valuers delivering precise Appraisal Reviews based on real-time market data.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'icon' => 'Zap',
                'title' => 'Fast Turnaround',
                'description' => 'Receive your comprehensive report within 24-48 hours. No lengthy waiting periods.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'icon' => 'ShieldCheck',
                'title' => 'Secure Process',
                'description' => 'Bank-level encryption protects your property documents and personal data.',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'icon' => 'Smartphone',
                'title' => 'Digital First',
                'description' => 'Track progress, negotiate fees, and sign documents entirely online.',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
