<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('notes');
            $table->foreignId('applied_rule_id')->nullable()->constrained('transaction_rules')->nullOnDelete()->after('recurring_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('tags');
            $table->dropForeign(['applied_rule_id']);
            $table->dropColumn('applied_rule_id');
        });
    }
};
