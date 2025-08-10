<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Rule identification
            $table->string('name');
            $table->text('description')->nullable();

            // Rule conditions (all must match for rule to apply)
            $table->json('conditions'); // Array of conditions
            /* Example conditions structure:
            [
                {
                    "field": "description",
                    "operator": "contains",
                    "value": "Starbucks"
                },
                {
                    "field": "amount",
                    "operator": "greater_than",
                    "value": 100000  // in cents
                }
            ]
            */

            // Rule actions (what to do when conditions match)
            $table->json('actions'); // Array of actions
            /* Example actions structure:
            [
                {
                    "type": "set_category",
                    "category_id": 5
                },
                {
                    "type": "add_tag",
                    "tag": "subscription"
                },
                {
                    "type": "set_account",
                    "account_id": 2
                }
            ]
            */

            // Rule configuration
            $table->integer('priority')->default(0); // Higher priority rules run first
            $table->boolean('is_active')->default(true);
            $table->boolean('stop_processing')->default(false); // Stop processing other rules after this one matches
            $table->enum('apply_to', ['all', 'income', 'expense', 'transfer'])->default('all');

            // Statistics
            $table->integer('times_applied')->default(0);
            $table->timestamp('last_applied_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_active', 'priority']);
            $table->index('apply_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_rules');
    }
};
