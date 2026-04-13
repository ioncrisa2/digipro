<?php

use App\Support\SystemNavigation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 20);
            $table->string('workspace', 30)->default('public');
            $table->string('action_label', 150);
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('route_params')->nullable();
            $table->json('query_payload')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['workspace', 'event_type', 'occurred_at'], 'activity_logs_workspace_event_idx');
            $table->index(['user_id', 'occurred_at'], 'activity_logs_user_idx');
            $table->index(['method', 'status_code'], 'activity_logs_method_status_idx');
        });

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $permission = Permission::findOrCreate(SystemNavigation::MANAGE_ADMIN_ACTIVITY_LOGS, 'web');
            $superAdminRole = Role::findOrCreate((string) config('access-control.super_admin.name', 'super_admin'), 'web');

            $superAdminRole->givePermissionTo($permission);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            Permission::query()
                ->where('name', SystemNavigation::MANAGE_ADMIN_ACTIVITY_LOGS)
                ->where('guard_name', 'web')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::dropIfExists('activity_logs');
    }
};
