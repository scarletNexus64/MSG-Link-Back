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
        // Modifier l'ENUM pour ajouter les types 'audio' et 'video'
        DB::statement("ALTER TABLE group_messages MODIFY COLUMN type ENUM('text', 'image', 'audio', 'video', 'system') NOT NULL DEFAULT 'text'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Retour à l'ENUM original
        DB::statement("ALTER TABLE group_messages MODIFY COLUMN type ENUM('text', 'image', 'system') NOT NULL DEFAULT 'text'");
    }
};
