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
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_code', 24)->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('minecraft_username', 40)->nullable();
                $table->string('discord_username', 64)->nullable();
                $table->string('contact', 120)->nullable();
                $table->string('category', 32)->default('other')->index();
                $table->string('subject', 150);
                $table->text('message');
                $table->string('status', 24)->default('open')->index();
                $table->text('admin_note')->nullable();
                $table->boolean('discord_dispatched')->default(false);
                $table->text('discord_error')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 80)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('app_settings');
    }
};
