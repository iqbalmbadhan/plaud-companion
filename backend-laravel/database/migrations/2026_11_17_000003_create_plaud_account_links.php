<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plaud_oauth_clients')) {
            Schema::create('plaud_oauth_clients', function (Blueprint $table) {
                $table->id();
                $table->string('redirect_uri', 500)->unique();
                $table->string('client_id', 255);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('plaud_account_links')) {
            Schema::create('plaud_account_links', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('user_id', 36)->unique();
                $table->string('workspace_id', 36)->index();
                $table->text('access_token');            // encrypted cast
                $table->text('refresh_token')->nullable(); // encrypted cast
                $table->timestamp('expires_at')->nullable();
                $table->string('status', 30)->default('connected');
                $table->string('last_error', 255)->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->unsignedInteger('imported_count')->default(0);
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            });
        }

        Schema::table('transcripts', function (Blueprint $table) {
            if (!Schema::hasColumn('transcripts', 'external_source')) {
                $table->string('external_source', 30)->nullable();
                $table->string('external_id', 100)->nullable();
                $table->longText('summary_markdown')->nullable();
                $table->index(['workspace_id', 'external_source', 'external_id'], 'ix_transcripts_external');
            }
        });
    }

    public function down(): void
    {
        // @allow-destructive-migration: rollback of the Plaud account link feature
        Schema::table('transcripts', function (Blueprint $table) {
            if (Schema::hasColumn('transcripts', 'external_source')) {
                $table->dropIndex('ix_transcripts_external');
                $table->dropColumn(['external_source', 'external_id', 'summary_markdown']);
            }
        });
        Schema::dropIfExists('plaud_account_links');
        Schema::dropIfExists('plaud_oauth_clients');
    }
};
