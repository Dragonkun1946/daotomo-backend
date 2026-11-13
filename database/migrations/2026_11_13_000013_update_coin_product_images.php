<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        DB::table('products')
            ->where('slug', 'goi-coin-minecraft-100k')
            ->orWhere('game_reward', 'coin_100k')
            ->orWhere('slug', 'like', '%coin%')
            ->update([
                'image' => '/img/coins.png',
                'images' => json_encode(['/img/coins.png']),
            ]);
    }

    public function down(): void
    {
        // No-op
    }
};
