<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('sepay_transaction_id')->unique()->index();
            $table->string('gateway')->nullable();
            $table->string('transaction_date')->nullable();
            $table->string('account_number')->nullable()->index();
            $table->string('sub_account')->nullable();
            $table->string('transfer_type')->default('in'); // 'in', 'out'
            $table->decimal('transfer_amount', 14, 2)->default(0);
            $table->decimal('accumulated', 14, 2)->nullable();
            $table->string('code')->nullable()->index();
            $table->text('content')->nullable();
            $table->string('reference_code')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('success')->index(); // 'success', 'insufficient_amount', 'unmatched_order', 'cancelled_order', 'duplicate'
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
