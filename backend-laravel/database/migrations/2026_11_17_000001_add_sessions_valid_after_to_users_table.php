<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sessions and API tokens created before this moment are no longer accepted
     * (set when the password changes or an administrator signs the user out everywhere).
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'sessions_valid_after')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('sessions_valid_after')->nullable();
            });
        }
    }

    /**
     * // @allow-destructive-migration: rollback dropping sessions_valid_after column
     */
    public function down(): void
    {
        // @allow-destructive-migration: rollback dropping sessions_valid_after column
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'sessions_valid_after')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('sessions_valid_after');
            });
        }
    }
};
