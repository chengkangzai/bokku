<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')
                ->nullable()
                ->after('category_id')
                ->constrained('recurring_transactions')
                ->onDelete('set null');

            $table->index('recurring_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['recurring_transaction_id']);
            $table->dropColumn('recurring_transaction_id');
        });
    }
};
