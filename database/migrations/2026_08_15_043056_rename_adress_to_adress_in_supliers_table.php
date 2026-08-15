<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supliers', function (Blueprint $table) {
            $table->renameColumn('adress', 'address');
        });
    }

    public function down(): void
    {
        Schema::table('supliers', function (Blueprint $table) {
            $table->renameColumn('address', 'adress');
        });
    }
};
