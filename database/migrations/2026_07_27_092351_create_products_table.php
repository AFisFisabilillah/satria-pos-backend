<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->string('image');
            $table->boolean('active')->default(true);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('sale_price');
            $table->timestamps();
            $table->unique(['code']);
            $table->unique(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
