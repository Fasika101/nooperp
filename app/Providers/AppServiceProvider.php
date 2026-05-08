<?php

namespace App\Providers;

use App\Models\BankTransaction;
use App\Models\BranchProductStock;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Observers\BankTransactionObserver;
use App\Observers\BranchProductStockObserver;
use App\Observers\ExpenseObserver;
use App\Observers\OrderObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductObserver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Order::observe(OrderObserver::class);
        Payment::observe(PaymentObserver::class);
        BankTransaction::observe(BankTransactionObserver::class);
        BranchProductStock::observe(BranchProductStockObserver::class);
        Expense::observe(ExpenseObserver::class);
        Product::observe(ProductObserver::class);
    }
}
