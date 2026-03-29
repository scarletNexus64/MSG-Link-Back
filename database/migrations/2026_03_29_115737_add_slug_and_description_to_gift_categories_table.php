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
        Schema::table('gift_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('gift_categories', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('gift_categories', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
        });

        // Générer les slugs pour les catégories existantes
        DB::statement('UPDATE gift_categories SET slug = LOWER(REPLACE(name, " ", "-")) WHERE slug IS NULL OR slug = ""');

        // Maintenant rendre le slug unique si ce n'est pas déjà le cas
        if (!DB::selectOne("SHOW INDEXES FROM gift_categories WHERE Key_name = 'gift_categories_slug_unique'")) {
            Schema::table('gift_categories', function (Blueprint $table) {
                $table->unique('slug');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_categories', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description']);
        });
    }
};
