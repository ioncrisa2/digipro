<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')
            ->where('title', 'Accurate Valuations')
            ->where('description', 'Certified surveyors providing precise appraisals based on real-time market data.')
            ->update([
                'description' => 'Certified KJPP valuers delivering precise desk appraisals based on real-time market data.',
                'updated_at' => now(),
            ]);

        DB::table('faqs')
            ->where('question', 'Is the appraisal officially recognized?')
            ->where('answer', 'Yes, all appraisals are conducted by licensed KJPP surveyors (MAPPI Cert.). Our reports are recognized by financial institutions and courts.')
            ->update([
                'answer' => 'Yes, all appraisals are conducted by licensed KJPP valuers (MAPPI Cert.) through a desk appraisal approach. Our reports are recognized by financial institutions and courts.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('features')
            ->where('title', 'Accurate Valuations')
            ->where('description', 'Certified KJPP valuers delivering precise desk appraisals based on real-time market data.')
            ->update([
                'description' => 'Certified surveyors providing precise appraisals based on real-time market data.',
                'updated_at' => now(),
            ]);

        DB::table('faqs')
            ->where('question', 'Is the appraisal officially recognized?')
            ->where('answer', 'Yes, all appraisals are conducted by licensed KJPP valuers (MAPPI Cert.) through a desk appraisal approach. Our reports are recognized by financial institutions and courts.')
            ->update([
                'answer' => 'Yes, all appraisals are conducted by licensed KJPP surveyors (MAPPI Cert.). Our reports are recognized by financial institutions and courts.',
                'updated_at' => now(),
            ]);
    }
};
