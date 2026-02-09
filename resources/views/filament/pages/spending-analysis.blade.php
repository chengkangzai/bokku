<x-filament-panels::page class="financial-overview-page">

    {{-- Summary Metrics Grid --}}
    <div class="summary-metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Total Balance</div>
            <div class="metric-value">MYR {{ number_format($this->getSummaryMetrics()['total_balance'] / 100, 2) }}</div>
            <div class="metric-trend">All Accounts</div>
        </div>

        <div class="metric-card">
            <div class="metric-label">Monthly Income</div>
            <div class="metric-value metric-value-positive">MYR {{ number_format($this->getSummaryMetrics()['monthly_income'] / 100, 2) }}</div>
            <div class="metric-trend">This Month</div>
        </div>

        <div class="metric-card">
            <div class="metric-label">Monthly Expenses</div>
            <div class="metric-value metric-value-negative">MYR {{ number_format($this->getSummaryMetrics()['monthly_expenses'] / 100, 2) }}</div>
            <div class="metric-trend">This Month</div>
        </div>

        <div class="metric-card">
            <div class="metric-label">Savings Rate</div>
            <div class="metric-value">{{ $this->getSummaryMetrics()['savings_rate'] }}%</div>
            <div class="metric-trend">Income vs Expenses</div>
        </div>
    </div>

    {{-- Two Column Layout --}}
    <div class="overview-grid">

        {{-- Left Column --}}
        <div class="overview-column">

            {{-- Account Balances --}}
            <div class="glass-section">
                <h3 class="section-title">Account Balances</h3>
                <div class="accounts-list">
                    @forelse($this->getAccountsData() as $account)
                        <div class="account-item">
                            <div class="account-info">
                                <div class="account-name">{{ $account['name'] }}</div>
                                <div class="account-type">{{ $account['type'] }}</div>
                            </div>
                            <div class="account-balance {{ $account['is_positive'] ? 'balance-positive' : 'balance-negative' }}">
                                {{ $account['formatted_balance'] }}
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No accounts yet</div>
                    @endforelse
                </div>
            </div>

            {{-- Loan Progress --}}
            @if($this->getLoansData()->isNotEmpty())
                <div class="glass-section">
                    <h3 class="section-title">Loan Progress</h3>
                    <div class="loans-list">
                        @foreach($this->getLoansData() as $loan)
                            <div class="loan-item">
                                <div class="loan-header">
                                    <span class="loan-name">{{ $loan['name'] }}</span>
                                    <span class="loan-balance">MYR {{ $loan['formatted_balance'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Top Expenses --}}
            <div class="glass-section">
                <h3 class="section-title">Top Expenses</h3>
                <div class="expenses-list">
                    @forelse($this->getTopExpenseCategories() as $category)
                        <div class="expense-item">
                            <div class="expense-info">
                                <div class="expense-indicator" style="background-color: {{ $category->color }}"></div>
                                <div class="expense-name">{{ $category->name }}</div>
                            </div>
                            <div class="expense-amount">
                                <span class="expense-value">{{ $category->formatted_total }}</span>
                                <span class="expense-percentage">{{ $category->percentage }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No expenses this month</div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Right Column --}}
        <div class="overview-column">

            {{-- Smart Insights --}}
            <div class="glass-section insights-section">
                <h3 class="section-title">Smart Insights</h3>
                <div class="insights-list">
                    @foreach($this->getSmartInsights() as $insight)
                        <div class="insight-item insight-{{ $insight['type'] }}">
                            <x-filament::icon
                                :icon="$insight['icon']"
                                class="insight-icon"
                            />
                            <div class="insight-message">{{ $insight['message'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Income Sources --}}
            <div class="glass-section">
                <h3 class="section-title">Income Sources</h3>
                <div class="income-list">
                    @forelse($this->getIncomeSourcesData() as $source)
                        <div class="income-item">
                            <div class="income-name">{{ $source->name }}</div>
                            <div class="income-amount">{{ $source->formatted_total }}</div>
                        </div>
                    @empty
                        <div class="empty-state">No income this month</div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

    {{-- Widgets Section (Existing Charts) --}}
    <div class="widgets-section">
        <h3 class="section-title">Detailed Analytics</h3>
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
        />
    </div>

</x-filament-panels::page>
