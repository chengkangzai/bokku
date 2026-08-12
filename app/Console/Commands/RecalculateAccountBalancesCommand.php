<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

class RecalculateAccountBalancesCommand extends Command
{
    protected $signature = 'accounts:recalculate-balances';

    protected $description = 'Recalculate every account balance from its initial balance and transactions';

    public function handle(): int
    {
        $accounts = Account::query()->get();

        $accounts->each(function (Account $account): void {
            $previousBalance = $account->balance;

            $this->info("Recalculating `{$account->name}` (id {$account->id})...");

            $account->updateBalance();

            if ($previousBalance != $account->balance) {
                $this->comment("  {$account->currency} ".number_format($previousBalance, 2).' -> '.number_format($account->balance, 2));
            }
        });

        $this->comment("Recalculated {$accounts->count()} accounts.");

        return self::SUCCESS;
    }
}
