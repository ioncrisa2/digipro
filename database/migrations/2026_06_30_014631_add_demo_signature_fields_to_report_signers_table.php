<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_signers', function (Blueprint $table): void {
            $table->string('demo_signature_path', 500)->nullable()->after('peruri_last_checked_at');
            $table->string('demo_signature_mime', 80)->nullable()->after('demo_signature_path');
            $table->string('demo_signature_hash', 100)->nullable()->after('demo_signature_mime');
            $table->dateTime('demo_signature_updated_at')->nullable()->after('demo_signature_hash');
            $table->foreignId('demo_signature_updated_by')
                ->nullable()
                ->after('demo_signature_updated_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_signers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('demo_signature_updated_by');
            $table->dropColumn([
                'demo_signature_path',
                'demo_signature_mime',
                'demo_signature_hash',
                'demo_signature_updated_at',
            ]);
        });
    }
};
