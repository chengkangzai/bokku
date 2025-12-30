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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('payee_id')->nullable()->after('category_id')->constrained()->onDelete('set null');
            $table->index('payee_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['payee_id']);
            $table->dropIndex(['payee_id']);
            $table->dropColumn('payee_id');
        });
    }
};
