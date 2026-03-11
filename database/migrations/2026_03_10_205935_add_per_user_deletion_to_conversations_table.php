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
            // Timestamps de suppression par utilisateur
            $table->timestamp('deleted_by_participant_one_at')->nullable()->after('deleted_at');
            $table->timestamp('deleted_by_participant_two_at')->nullable()->after('deleted_by_participant_one_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('deleted_by_participant_one_at');
            $table->dropColumn('deleted_by_participant_two_at');
        });
    }
};
