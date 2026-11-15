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
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->rememberToken();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * // @allow-destructive-migration: rollback dropping remember_token column
     */
    public function down(): void
    {
        // @allow-destructive-migration: rollback dropping remember_token column
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropRememberToken();
            });
        }
    }
};
