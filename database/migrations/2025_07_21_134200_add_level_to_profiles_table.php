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
        Schema::table('profiles', function (Blueprint $table) {
            // Add the 'level' column after the 'role' column for better organization.
            // It's an unsigned tiny integer because we only expect small positive values (1, 2, 3).
            // Defaulting to 1 for any new collaborator profiles.
            $table->unsignedTinyInteger('level')->default(1)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
