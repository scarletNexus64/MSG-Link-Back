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
        Schema::table('settings', function (Blueprint $table) {
            $table->string('freemopay_base_url')->nullable()->after('value');
            $table->string('freemopay_app_key')->nullable();
            $table->string('freemopay_secret_key')->nullable();
            $table->string('freemopay_callback_url')->nullable();
            $table->integer('freemopay_init_payment_timeout')->default(60);
            $table->integer('freemopay_status_check_timeout')->default(30);
            $table->integer('freemopay_token_timeout')->default(30);
            $table->integer('freemopay_token_cache_duration')->default(3000);
            $table->integer('freemopay_max_retries')->default(2);
            $table->decimal('freemopay_retry_delay', 3, 1)->default(0.5);
            $table->boolean('freemopay_active')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'freemopay_base_url',
                'freemopay_app_key',
                'freemopay_secret_key',
                'freemopay_callback_url',
                'freemopay_init_payment_timeout',
                'freemopay_status_check_timeout',
                'freemopay_token_timeout',
                'freemopay_token_cache_duration',
                'freemopay_max_retries',
                'freemopay_retry_delay',
                'freemopay_active'
            ]);
        });
    }
};
