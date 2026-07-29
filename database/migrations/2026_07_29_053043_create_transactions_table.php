<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->unsignedBigInteger('total_price');
            $table->unsignedBigInteger('paid_amount');
            $table->unsignedBigInteger('change_amount');
            $table->string('payment_method');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
