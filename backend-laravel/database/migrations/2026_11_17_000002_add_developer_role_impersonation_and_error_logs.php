<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Developer: the platform-level role above super-admin (updates, users, impersonation, debug log).
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'is_developer')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_developer')->default(false);
            });
        }

        // Records the real person behind an action taken while impersonating another user.
        if (Schema::hasTable('audit_events') && !Schema::hasColumn('audit_events', 'impersonator_user_id')) {
            Schema::table('audit_events', function (Blueprint $table): void {
                $table->string('impersonator_user_id', 36)->nullable()->index();
            });
        }

        // Server and browser errors for the developer debug log. Never stores transcript text,
        // request bodies, SQL bindings, tokens or secrets.
        if (!Schema::hasTable('error_logs')) {
            Schema::create('error_logs', function (Blueprint $table): void {
                $table->string('id', 36)->primary();
                $table->string('reference', 20)->unique();
                $table->string('source', 20);            // server | browser
                $table->string('level', 20)->default('error');
                $table->string('exception_class', 191)->nullable();
                $table->text('message');
                $table->string('location', 255)->nullable(); // file:line or page URL path
                $table->string('method', 10)->nullable();
                $table->string('path', 255)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->string('user_id', 36)->nullable()->index();
                $table->string('impersonator_user_id', 36)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
    }

    /**
     * // @allow-destructive-migration: rollback of developer role, impersonation column and error log
     */
    public function down(): void
    {
        // @allow-destructive-migration: rollback of developer role, impersonation column and error log
        Schema::dropIfExists('error_logs');
        if (Schema::hasTable('audit_events') && Schema::hasColumn('audit_events', 'impersonator_user_id')) {
            Schema::table('audit_events', function (Blueprint $table): void {
                $table->dropIndex(['impersonator_user_id']);
                $table->dropColumn('impersonator_user_id');
            });
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_developer')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('is_developer');
            });
        }
    }
};
