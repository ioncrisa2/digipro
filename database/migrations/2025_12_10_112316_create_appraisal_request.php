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
        Schema::create('appraisal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('guideline_set_id')
                ->nullable()
                ->constrained('ref_guideline_sets');
            $table->string('request_number')->unique();
            $table->enum('purpose', ['jual_beli', 'penjaminan', 'lelang']);
            $table->enum('status', [
                'draft',
                'submitted',
                'docs_incomplete',
                'verified',
                'waiting_offer',
                'offer_sent',
                'waiting_signature',
                'contract_signed',
                'valuation_in_progress',
                'valuation_completed',
                'report_ready',
                'completed',
                'cancelled'
            ])->default('draft');
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('client_name')->nullable();
            $table->text('client_address')->nullable();
            $table->string('client_spk_number')->nullable();
            $table->text('user_request_note')->nullable();
            $table->string('contract_number')->nullable()->unique();
            $table->date('contract_date')->nullable();
            $table->unsignedInteger('contract_sequence')->nullable();
            $table->string('contract_office_code', 10)->nullable();
            $table->unsignedTinyInteger('contract_month')->nullable();
            $table->unsignedSmallInteger('contract_year')->nullable();
            $table->enum('contract_status', [
                'none',
                'draft',
                'sent_to_client',
                'waiting_signature',
                'signed',
                'negotiation',
                'cancelled'
            ])->default('none');

            $table->enum('report_type', ['terinci', 'singkat'])->nullable();

             $table->enum('report_format', ['digital', 'physical', 'both'])
                ->default('digital')
                ->after('report_type')
                ->comment('Format laporan: digital (PDF), cetak fisik, atau keduanya');

            $table->unsignedSmallInteger('physical_copies_count')
                ->default(0)
                ->after('report_format')
                ->comment('Jumlah salinan fisik yang dicetak (0 jika digital only)');

            $table->text('report_delivery_address')
                ->nullable()
                ->after('physical_copies_count')
                ->comment('Alamat khusus pengiriman laporan (jika berbeda dari client_address)');

            $table->string('report_delivery_recipient_name', 100)
                ->nullable()
                ->after('report_delivery_address')
                ->comment('Nama penerima laporan (jika berbeda dari client_name)');

            $table->string('report_delivery_recipient_phone', 30)
                ->nullable()
                ->after('report_delivery_recipient_name')
                ->comment('Nomor telepon penerima');

            // 2) Report Generation Tracking (Digital)
            $table->timestamp('report_generated_at')
                ->nullable()
                ->after('verified_at')
                ->comment('Waktu laporan PDF selesai di-generate');

            $table->foreignId('report_generated_by')
                ->nullable()
                ->after('report_generated_at')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User yang generate laporan PDF');

            $table->string('report_pdf_path', 500)
                ->nullable()
                ->after('report_generated_by')
                ->comment('Path file PDF laporan (relative path di disk local)');

            $table->unsignedBigInteger('report_pdf_size')
                ->nullable()
                ->after('report_pdf_path')
                ->comment('Ukuran file PDF dalam bytes');

            // 3) Physical Report Tracking
            $table->timestamp('physical_report_printed_at')
                ->nullable()
                ->after('report_pdf_size')
                ->comment('Waktu laporan fisik selesai dicetak');

            $table->foreignId('physical_report_printed_by')
                ->nullable()
                ->after('physical_report_printed_at')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User yang mencetak laporan');

            $table->timestamp('physical_report_shipped_at')
                ->nullable()
                ->after('physical_report_printed_by')
                ->comment('Waktu laporan fisik dikirim');

            $table->timestamp('physical_report_delivered_at')
                ->nullable()
                ->after('physical_report_shipped_at')
                ->comment('Waktu laporan fisik terkirim/diambil');

            $table->string('physical_report_tracking_number', 50)
                ->nullable()
                ->after('physical_report_delivered_at')
                ->comment('Nomor resi pengiriman (JNE, TIKI, dll)');

            $table->string('physical_report_courier', 50)
                ->nullable()
                ->after('physical_report_tracking_number')
                ->comment('Nama kurir/ekspedisi');

            $table->text('physical_report_notes')
                ->nullable()
                ->after('physical_report_courier')
                ->comment('Catatan pengiriman laporan fisik');
            $table->unsignedSmallInteger('valuation_duration_days')->nullable();
            $table->bigInteger('fee_total')->nullable();
            $table->boolean('fee_has_dp')->default(false);
            $table->decimal('fee_dp_percent', 5, 2)->nullable(); // default 50%
            $table->unsignedSmallInteger('offer_validity_days')->nullable();
            $table->timestamps();

            $table->index('report_format');
            $table->index('report_generated_at');
            $table->index('physical_report_printed_at');
            $table->index('physical_report_delivered_at');

            $table->index('report_generated_by');
            $table->index('physical_report_printed_by');

            // composite indexes for list/filter pages
            $table->index(['report_format', 'report_generated_at']);
            $table->index(['report_format', 'physical_report_delivered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appraisal_requests');
    }
};
