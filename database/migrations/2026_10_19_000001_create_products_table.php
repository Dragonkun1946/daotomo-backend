<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('category')->index(); // 'ranks', 'crates', 'currency', 'items'
            $table->string('image')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_quantity')->default(-1); // -1 = vô hạn
            $table->boolean('is_active')->default(true);
            $table->boolean('featured')->default(false);
            $table->json('tags')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
