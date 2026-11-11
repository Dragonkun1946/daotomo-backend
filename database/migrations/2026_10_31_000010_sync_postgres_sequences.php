<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $tables = [
                'users',
                'products',
                'orders',
                'order_items',
                'checkins',
                'payment_transactions',
                'game_link_pins',
                'game_delivery_queue',
                'game_delivery_queues',
                'personal_access_tokens',
            ];

            foreach ($tables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'id')) {
                    try {
                        DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)");
                    } catch (\Throwable $e) {
                        // ignore if table doesn't have serial id
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
