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
        Schema::table('groups', function (Blueprint $table) {
            // Permet aux groupes privés d'apparaître dans la recherche/découverte
            $table->boolean('is_discoverable')->default(true)->after('is_public');
            $table->index('is_discoverable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex(['is_discoverable']);
            $table->dropColumn('is_discoverable');
        });
    }
};
