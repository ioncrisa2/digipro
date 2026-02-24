<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->index(['user_id', 'requested_at'], 'appraisal_requests_user_requested_at_idx');
            $table->index(['user_id', 'status', 'requested_at'], 'appraisal_requests_user_status_requested_at_idx');
            $table->index('status', 'appraisal_requests_status_idx');
        });

        Schema::table('terms_documents', function (Blueprint $table) {
            $table->index('is_active', 'terms_documents_is_active_idx');
            $table->index('published_at', 'terms_documents_published_at_idx');
        });

        Schema::table('privacy_policies', function (Blueprint $table) {
            $table->index('is_active', 'privacy_policies_is_active_idx');
            $table->index('published_at', 'privacy_policies_published_at_idx');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->index('is_active', 'features_is_active_idx');
            $table->index('sort_order', 'features_sort_order_idx');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->index('is_active', 'faqs_is_active_idx');
            $table->index('sort_order', 'faqs_sort_order_idx');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->index('is_active', 'testimonials_is_active_idx');
            $table->index('sort_order', 'testimonials_sort_order_idx');
        });

        Schema::table('consent_document', function (Blueprint $table) {
            $table->index(['code', 'status', 'published_at'], 'consent_document_code_status_published_idx');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->index('status', 'contact_messages_status_idx');
            $table->index('created_at', 'contact_messages_created_at_idx');
        });

        Schema::table('appraisal_user_consents', function (Blueprint $table) {
            $table->unique(['user_id', 'consent_document_id'], 'appraisal_user_consents_user_doc_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appraisal_requests', function (Blueprint $table) {
            $table->dropIndex('appraisal_requests_user_requested_at_idx');
            $table->dropIndex('appraisal_requests_user_status_requested_at_idx');
            $table->dropIndex('appraisal_requests_status_idx');
        });

        Schema::table('terms_documents', function (Blueprint $table) {
            $table->dropIndex('terms_documents_is_active_idx');
            $table->dropIndex('terms_documents_published_at_idx');
        });

        Schema::table('privacy_policies', function (Blueprint $table) {
            $table->dropIndex('privacy_policies_is_active_idx');
            $table->dropIndex('privacy_policies_published_at_idx');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropIndex('features_is_active_idx');
            $table->dropIndex('features_sort_order_idx');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex('faqs_is_active_idx');
            $table->dropIndex('faqs_sort_order_idx');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropIndex('testimonials_is_active_idx');
            $table->dropIndex('testimonials_sort_order_idx');
        });

        Schema::table('consent_document', function (Blueprint $table) {
            $table->dropIndex('consent_document_code_status_published_idx');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex('contact_messages_status_idx');
            $table->dropIndex('contact_messages_created_at_idx');
        });

        Schema::table('appraisal_user_consents', function (Blueprint $table) {
            $table->dropUnique('appraisal_user_consents_user_doc_unique');
        });
    }
};