<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('workspace_id', 36)->index();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('active');
            $table->string('created_by_user_id', 36);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        Schema::create('transcripts', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('workspace_id', 36)->index();
            $table->string('project_id', 36)->nullable()->index();
            $table->string('created_by_user_id', 36);
            $table->string('title', 255)->index();
            $table->text('description')->nullable();
            $table->string('source_type', 50)->default('manual_upload');
            $table->timestamp('recorded_at')->nullable();
            $table->string('language', 20)->nullable()->default('en');
            $table->string('status', 50)->default('imported');
            $table->longText('content_text');
            $table->string('content_hash', 64)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'created_at'], 'ix_transcripts_workspace_date');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });

        Schema::create('transcript_segments', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('transcript_id', 36)->index();
            $table->integer('sequence_number');
            $table->string('speaker_label', 100)->nullable();
            $table->integer('start_ms')->nullable();
            $table->integer('end_ms')->nullable();
            $table->text('text');

            $table->unique(['transcript_id', 'sequence_number'], 'uq_transcript_segment_seq');
            $table->index(['transcript_id', 'sequence_number'], 'ix_segment_transcript_seq');
            $table->foreign('transcript_id')->references('id')->on('transcripts')->cascadeOnDelete();
        });

        Schema::create('transcript_files', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('transcript_id', 36)->index();
            $table->string('workspace_id', 36)->index();
            $table->string('storage_key', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->integer('size_bytes');
            $table->string('sha256', 64);
            $table->string('file_role', 50)->default('original');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('transcript_id')->references('id')->on('transcripts')->cascadeOnDelete();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }
};
