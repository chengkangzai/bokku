<?php

use App\Enums\AccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        $this->setInitialSortOrders();
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }

    private function setInitialSortOrders(): void
    {
        $assetTypes = [AccountType::Bank->value, AccountType::Cash->value];
        $liabilityTypes = [AccountType::CreditCard->value, AccountType::Loan->value];

        foreach ([$assetTypes, $liabilityTypes] as $typeGroup) {
            $accounts = DB::table('accounts')
                ->whereIn('type', $typeGroup)
                ->orderBy('name')
                ->get();

            foreach ($accounts->groupBy('user_id') as $userId => $userAccounts) {
                foreach ($userAccounts->values() as $index => $account) {
                    DB::table('accounts')
                        ->where('id', $account->id)
                        ->update(['sort_order' => $index]);
                }
            }
        }
    }
};
