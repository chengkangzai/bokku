<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->string('period', 50)->default('monthly')->change();
        });

        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->string('type', 50)->change();
            $table->string('frequency', 50)->change();
        });

        Schema::table('transaction_rules', function (Blueprint $table) {
            $table->string('apply_to', 50)->default('all')->change();
        });
    }

    public function down(): void
    {
        // Intentionally left as strings; application-level enums validate values.
    }
};
