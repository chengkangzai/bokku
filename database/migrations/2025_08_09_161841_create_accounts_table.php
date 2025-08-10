<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['bank', 'cash', 'credit_card', 'loan']);
            $table->string('icon')->nullable();
            $table->string('color')->default('#3b82f6'); // Blue
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('initial_balance')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('account_number')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
