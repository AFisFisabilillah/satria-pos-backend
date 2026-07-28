<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_supply_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_supply_id');
            $table->foreignId('product_id');
            $table->unsignedInteger('quantity_in');
            $table->unsignedInteger('quantity_remaining');
            $table->decimal('purchase_price');
            $table->date('expired_at');
            $table->index(["product_id", "created_at"]);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_supply_items');
    }
};
