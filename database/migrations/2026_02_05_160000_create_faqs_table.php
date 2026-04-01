<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('faqs')->insert([
            [
                'question' => 'How long does the process take?',
                'answer' => 'You will typically receive your professional appraisal report within 24-48 hours after submitting all required documents.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'Is the appraisal officially recognized?',
                'answer' => 'Yes, all appraisals are conducted by licensed KJPP valuers (MAPPI Cert.) through an Appraisal Review approach. Our reports are recognized by financial institutions and courts.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'What documents are required?',
                'answer' => 'Basic requirements include Land Certificate (SHM/HGB), PBB Tax Receipt, and property photos. Additional docs may be needed for commercial assets.',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
