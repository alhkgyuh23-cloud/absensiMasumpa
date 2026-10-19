<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tingkat');   // 10, 11, 12
            $table->string('rombel', 5);              // A, B, C, D, E, F, ...
            $table->string('name')->unique();         // otomatis: "X - A"
            $table->timestamps();

            $table->unique(['tingkat', 'rombel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};