<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_midtrans_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_request_id')
                ->constrained('appraisal_requests')
                ->cascadeOnDelete();

            $table->bigInteger('amount')->default(0);
            $table->enum('method', ['manual', 'gateway'])->default('manual');
            $table->string('gateway')->nullable();
            $table->string('external_payment_id', 120)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'rejected', 'refunded', 'expired'])
                ->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('proof_file_path', 500)->nullable();
            $table->string('proof_original_name', 255)->nullable();
            $table->string('proof_mime', 120)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->enum('proof_type', ['upload', 'gateway_id'])->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('method');
            $table->index('gateway');
            $table->index('external_payment_id');
            $table->index(['appraisal_request_id', 'status']);
        });

        $rows = DB::table('payments')->orderBy('id')->get();

        foreach ($rows as $row) {
            DB::table('payments_midtrans_tmp')->insert([
                'id' => $row->id,
                'appraisal_request_id' => $row->appraisal_request_id,
                'amount' => $row->amount,
                'method' => $row->method,
                'gateway' => $row->gateway,
                'external_payment_id' => $row->external_payment_id,
                'status' => $row->status,
                'paid_at' => $row->paid_at,
                'proof_file_path' => $row->proof_file_path,
                'proof_original_name' => $row->proof_original_name,
                'proof_mime' => $row->proof_mime,
                'proof_size' => $row->proof_size,
                'proof_type' => $row->proof_type,
                'metadata' => $row->metadata,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('payments');
        Schema::rename('payments_midtrans_tmp', 'payments');
    }

    public function down(): void
    {
        Schema::create('payments_midtrans_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_request_id')
                ->constrained('appraisal_requests')
                ->cascadeOnDelete();

            $table->bigInteger('amount')->default(0);
            $table->enum('method', ['manual', 'gateway'])->default('manual');
            $table->string('gateway')->nullable();
            $table->string('external_payment_id', 120)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'rejected', 'refunded'])
                ->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('proof_file_path', 500)->nullable();
            $table->string('proof_original_name', 255)->nullable();
            $table->string('proof_mime', 120)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->enum('proof_type', ['upload', 'gateway_id'])->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('method');
            $table->index('gateway');
            $table->index('external_payment_id');
            $table->index(['appraisal_request_id', 'status']);
        });

        $rows = DB::table('payments')->orderBy('id')->get();

        foreach ($rows as $row) {
            DB::table('payments_midtrans_tmp')->insert([
                'id' => $row->id,
                'appraisal_request_id' => $row->appraisal_request_id,
                'amount' => $row->amount,
                'method' => $row->method,
                'gateway' => $row->gateway,
                'external_payment_id' => $row->external_payment_id,
                'status' => $row->status === 'expired' ? 'failed' : $row->status,
                'paid_at' => $row->paid_at,
                'proof_file_path' => $row->proof_file_path,
                'proof_original_name' => $row->proof_original_name,
                'proof_mime' => $row->proof_mime,
                'proof_size' => $row->proof_size,
                'proof_type' => $row->proof_type,
                'metadata' => $row->metadata,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('payments');
        Schema::rename('payments_midtrans_tmp', 'payments');
    }
};
