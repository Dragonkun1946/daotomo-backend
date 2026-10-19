<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique()->index();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('minecraft_username')->nullable();
            $table->text('note')->nullable();
            $table->string('payment_method')->default('qr'); // 'qr', 'card', 'bank', 'coins'
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('order_status')->default('pending')->index(); // 'pending', 'processing', 'completed', 'cancelled', 'delivered'
            $table->string('payment_status')->default('pending')->index(); // 'pending', 'paid', 'failed', 'refunded'
            $table->string('status')->default('pending'); // legacy compatibility
            $table->text('payment_proof')->nullable();
            $table->string('transfer_content')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->text('admin_note')->nullable();
            $table->json('status_history')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
