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
        Schema::create('transaction_item_cogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_item_id')->constrained('transaction_items')->cascadeOnDelete();
            $table->foreignId('stock_supply_item_id')->constrained('stock_supply_items');
            $table->unsignedInteger('quantity_deducted');
            $table->decimal('purchase_price', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_item_cogs');
    }
};
