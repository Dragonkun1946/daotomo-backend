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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'minecraft_uuid')) {
                $table->string('minecraft_uuid', 64)->nullable()->unique()->after('minecraft_username');
            }
            if (!Schema::hasColumn('users', 'game_linked_at')) {
                $table->timestamp('game_linked_at')->nullable()->after('minecraft_uuid');
            }
        });

        // Bảng lưu mã PIN handshake tạm thời khi người chơi gõ /link in-game
        Schema::create('game_link_pins', function (Blueprint $table) {
            $table->id();
            $table->string('minecraft_username', 32);
            $table->string('minecraft_uuid', 64)->index();
            $table->string('pin_code', 16)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Bảng hàng đợi các lệnh cần chạy trong Minecraft (phần thưởng điểm danh, đơn hàng SePay...)
        Schema::create('game_delivery_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('game_username', 32);
            $table->string('game_uuid', 64)->nullable();
            $table->text('command');
            $table->string('type', 32)->default('order'); // 'order', 'checkin_reward'
            $table->string('status', 16)->default('pending'); // 'pending', 'completed', 'failed'
            $table->integer('attempts')->default(0);
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_delivery_queue');
        Schema::dropIfExists('game_link_pins');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'minecraft_uuid')) {
                $table->dropColumn('minecraft_uuid');
            }
            if (Schema::hasColumn('users', 'game_linked_at')) {
                $table->dropColumn('game_linked_at');
            }
        });
    }
};
