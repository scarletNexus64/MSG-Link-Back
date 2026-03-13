<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Supprimer la contrainte de clé étrangère
        Schema::table('gift_transactions', function (Blueprint $table) {
            $table->dropForeign(['gift_id']);
        });

        // Modifier la colonne pour la rendre nullable
        DB::statement('ALTER TABLE gift_transactions MODIFY gift_id BIGINT UNSIGNED NULL');

        // Recréer la contrainte de clé étrangère avec nullable
        Schema::table('gift_transactions', function (Blueprint $table) {
            $table->foreign('gift_id')->references('id')->on('gifts')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la contrainte de clé étrangère
        Schema::table('gift_transactions', function (Blueprint $table) {
            $table->dropForeign(['gift_id']);
        });

        // Remettre la colonne en NOT NULL
        DB::statement('ALTER TABLE gift_transactions MODIFY gift_id BIGINT UNSIGNED NOT NULL');

        // Recréer la contrainte de clé étrangère
        Schema::table('gift_transactions', function (Blueprint $table) {
            $table->foreign('gift_id')->references('id')->on('gifts')->onDelete('restrict');
        });
    }
};
