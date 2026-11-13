<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('email', 255)->unique();
            $table->string('display_name', 100);
            $table->string('password_hash', 255);
            $table->string('status', 50)->default('active');
            $table->boolean('is_super_admin')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workspaces', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('name', 100);
            $table->string('type', 50)->default('personal');
            $table->string('owner_user_id', 36);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('workspace_members', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('workspace_id', 36)->index();
            $table->string('user_id', 36)->index();
            $table->string('role', 50)->default('editor');
            $table->string('status', 50)->default('active');
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['workspace_id', 'user_id'], 'uq_workspace_member');
            $table->index(['workspace_id', 'user_id'], 'ix_workspace_member_lookup');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('workspace_id', 36)->index();
            $table->string('email', 255);
            $table->string('role', 50);
            $table->string('token_hash', 255)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->string('created_by_user_id', 36);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });
    }
};
