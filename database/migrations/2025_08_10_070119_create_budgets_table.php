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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount'); // Amount in cents
            $table->enum('period', ['monthly', 'weekly', 'annual'])->default('monthly');
            $table->date('start_date');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_rollover')->default(false);
            $table->timestamps();

            // Ensure unique budget per category per user
            $table->unique(['user_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
