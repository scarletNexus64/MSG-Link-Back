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
        Schema::table('conversations', function (Blueprint $table) {
            // Supprimer les anciennes colonnes (mal nommées)
            $table->dropColumn(['deleted_by_participant_one_at', 'deleted_by_participant_two_at']);

            // Ajouter les nouvelles colonnes avec le système is_hidden
            $table->boolean('is_hidden_by_participant_one')->default(false)->after('message_count');
            $table->timestamp('hidden_by_participant_one_at')->nullable()->after('is_hidden_by_participant_one');
            $table->boolean('is_hidden_by_participant_two')->default(false)->after('hidden_by_participant_one_at');
            $table->timestamp('hidden_by_participant_two_at')->nullable()->after('is_hidden_by_participant_two');

            // Index pour optimiser les queries de liste de conversations
            $table->index(['participant_one_id', 'is_hidden_by_participant_one', 'last_message_at'], 'idx_p1_hidden_msg');
            $table->index(['participant_two_id', 'is_hidden_by_participant_two', 'last_message_at'], 'idx_p2_hidden_msg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Supprimer les index
            $table->dropIndex('idx_p1_hidden_msg');
            $table->dropIndex('idx_p2_hidden_msg');

            // Supprimer les nouvelles colonnes
            $table->dropColumn([
                'is_hidden_by_participant_one',
                'hidden_by_participant_one_at',
                'is_hidden_by_participant_two',
                'hidden_by_participant_two_at'
            ]);

            // Restaurer les anciennes colonnes
            $table->timestamp('deleted_by_participant_one_at')->nullable();
            $table->timestamp('deleted_by_participant_two_at')->nullable();
        });
    }
};
