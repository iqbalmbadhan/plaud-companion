<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('workspace_id', 36)->index();
            $table->string('requested_by_user_id', 36);
            $table->string('status', 50)->default('queued');
            $table->string('template', 50)->default('clean_transcript');
            $table->json('settings')->nullable();
            $table->integer('total_items')->default(0);
            $table->integer('completed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->string('archive_storage_key', 255)->nullable();
            $table->string('manifest_storage_key', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users');
        });

        Schema::create('export_job_items', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('export_job_id', 36)->index();
            $table->string('transcript_id', 36);
            $table->string('status', 50)->default('queued');
            $table->string('output_storage_key', 255)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->integer('attempt_count')->default(0);

            $table->foreign('export_job_id')->references('id')->on('export_jobs')->cascadeOnDelete();
            $table->foreign('transcript_id')->references('id')->on('transcripts')->cascadeOnDelete();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('workspace_id', 36)->nullable()->index();
            $table->string('actor_user_id', 36)->nullable()->index();
            $table->string('action', 100)->index();
            $table->string('resource_type', 100);
            $table->string('resource_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('provider_connections', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('workspace_id', 36)->index();
            $table->string('provider', 50);
            $table->text('api_key_encrypted')->nullable();
            $table->string('auth_mode', 50)->default('oauth');
            $table->boolean('enabled')->default(true);
            $table->string('default_model', 100)->nullable();
            $table->json('allowed_models')->nullable();
            $table->string('created_by_user_id', 36);
            $table->timestamps();

            $table->unique(['workspace_id', 'provider'], 'uq_workspace_provider');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });
    }
};
