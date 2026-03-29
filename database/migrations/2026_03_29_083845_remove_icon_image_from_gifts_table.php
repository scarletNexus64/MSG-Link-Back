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
        Schema::table('gifts', function (Blueprint $table) {
            // Supprimer la colonne icon_image car on utilise uniquement les icônes emoji
            $table->dropColumn('icon_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            // Restaurer la colonne icon_image si nécessaire
            $table->string('icon_image')->nullable()->after('icon');
        });
    }
};
