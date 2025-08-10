<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Transaction details
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->bigInteger('amount'); // Store in cents
            $table->string('description');
            
            // Accounts
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            
            // Recurrence settings
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'annual']);
            $table->integer('interval')->default(1); // e.g., every 2 weeks
            $table->integer('day_of_week')->nullable(); // 0-6 for weekly (Carbon's dayOfWeek: 0=Sunday, 6=Saturday)
            $table->integer('day_of_month')->nullable(); // 1-31 for monthly
            $table->integer('month_of_year')->nullable(); // 1-12 for annual
            
            // Scheduling
            $table->date('next_date'); // Next scheduled occurrence
            $table->timestamp('last_processed')->nullable(); // Last time transaction was created
            $table->date('start_date'); // When recurrence starts
            $table->date('end_date')->nullable(); // When recurrence ends (nullable for indefinite)
            
            // Options
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_process')->default(true); // Auto-create transactions
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'is_active']);
            $table->index(['next_date', 'is_active']);
            $table->index(['user_id', 'frequency']);
            $table->index('account_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};