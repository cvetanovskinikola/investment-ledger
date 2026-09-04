<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Services\PortfolioService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a few clients and a ledger of
     * movements for each, built through PortfolioService so the sample
     * data obeys the same rules as any real request would.
     */
    public function run(): void
    {
        $portfolio = app(PortfolioService::class);

        $ana = Client::create(['name' => 'Ana']);
        $portfolio->deposit($ana, '1000.00');
        $portfolio->buy($ana, 'AAPL', 5, '100.00');
        $portfolio->sell($ana, 'AAPL', 3, '120.00');
        // Ana ends up with 860.00 cash and 2 AAPL.

        $marko = Client::create(['name' => 'Marko']);
        $portfolio->deposit($marko, '5000.00');
        $portfolio->buy($marko, 'MSFT', 20, '50.00');
        $portfolio->buy($marko, 'GOOGL', 10, '150.00');
        $portfolio->withdraw($marko, '800.00');
        // Marko ends up with 1700.00 cash, 20 MSFT and 10 GOOGL.

        $elena = Client::create(['name' => 'Elena']);
        $portfolio->deposit($elena, '300.00');
        $portfolio->buy($elena, 'TSLA', 2, '140.00');
        // Elena ends up with 20.00 cash and 2 TSLA.
    }
}
