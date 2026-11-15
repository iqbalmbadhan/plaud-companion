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
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email', 255)->primary();
                $table->string('token', 255);
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * // @allow-destructive-migration: rollback dropping password_reset_tokens table
     */
    public function down(): void
    {
        // @allow-destructive-migration: rollback dropping password_reset_tokens table
        Schema::dropIfExists('password_reset_tokens');
    }
};
