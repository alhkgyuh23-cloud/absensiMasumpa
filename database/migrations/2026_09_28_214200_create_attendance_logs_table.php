<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scanned_at');
            $table->enum('type', ['masuk', 'pulang']);
            $table->enum('wa_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamps();

            $table->index('scanned_at');
            $table->index(['student_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};