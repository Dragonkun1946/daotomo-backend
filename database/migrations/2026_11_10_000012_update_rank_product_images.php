<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        $rankImages = [
            'rank-knight'   => ['reward' => 'rank_knight',   'image' => '/img/ranks/rank-knight.jpg'],
            'rank-legend'   => ['reward' => 'rank_legend',   'image' => '/img/ranks/rank-legend.jpg'],
            'rank-master'   => ['reward' => 'rank_master',   'image' => '/img/ranks/rank-master.jpg'],
            'rank-overlord' => ['reward' => 'rank_overlord', 'image' => '/img/ranks/rank-overlord.jpg'],
            'rank-god'      => ['reward' => 'rank_god',      'image' => '/img/ranks/rank-god.jpg'],
        ];

        foreach ($rankImages as $slug => $cfg) {
            DB::table('products')
                ->where('slug', $slug)
                ->orWhere('game_reward', $cfg['reward'])
                ->update([
                    'image' => $cfg['image'],
                    'images' => json_encode([$cfg['image']]),
                ]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
