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
        // Modifier l'ENUM pour ajouter le type 'gift'
        DB::statement("ALTER TABLE group_messages MODIFY COLUMN type ENUM('text', 'image', 'audio', 'video', 'gift', 'system') NOT NULL DEFAULT 'text'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Retour à l'ENUM sans 'gift'
        DB::statement("ALTER TABLE group_messages MODIFY COLUMN type ENUM('text', 'image', 'audio', 'video', 'system') NOT NULL DEFAULT 'text'");
    }
};
