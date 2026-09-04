<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_increases_cash_balance(): void
    {
        $client = Client::create(['name' => 'Ana']);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'deposit',
            'amount' => 1000,
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertOk()
            ->assertJson(['cash_balance' => '1000.00']);
    }

    public function test_withdrawal_decreases_cash_balance(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 1000);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'withdrawal',
            'amount' => 400,
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '600.00']);
    }

    public function test_withdrawal_beyond_balance_is_rejected_and_balance_is_unchanged(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 500);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'withdrawal',
            'amount' => 501,
        ])->assertStatus(422);

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '500.00']);
    }

    public function test_buy_decreases_cash_and_increases_holdings(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 1000);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 5,
            'price_per_unit' => 100,
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '500.00']);

        $this->getJson("/api/clients/{$client->id}/holdings")
            ->assertJson(['holdings' => ['AAPL' => 5]]);
    }

    public function test_buy_beyond_cash_balance_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 500);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 5,
            'price_per_unit' => 200,
        ])->assertStatus(422);

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '500.00']);

        $this->getJson("/api/clients/{$client->id}/holdings")
            ->assertJson(['holdings' => []]);
    }

    public function test_sell_beyond_owned_quantity_is_rejected_and_holdings_unchanged(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 1000);
        $this->buy($client, 'AAPL', 5, 100);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'sell',
            'instrument' => 'AAPL',
            'quantity' => 8,
            'price_per_unit' => 120,
        ])->assertStatus(422);

        $this->getJson("/api/clients/{$client->id}/holdings")
            ->assertJson(['holdings' => ['AAPL' => 5]]);
    }

    public function test_sell_at_a_different_price_than_purchase_is_allowed_and_credits_sale_proceeds(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 1000);
        $this->buy($client, 'AAPL', 5, 100);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'sell',
            'instrument' => 'AAPL',
            'quantity' => 3,
            'price_per_unit' => 120,
        ])->assertCreated();

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '860.00']);

        $this->getJson("/api/clients/{$client->id}/holdings")
            ->assertJson(['holdings' => ['AAPL' => 2]]);
    }

    /**
     * The exact walkthrough from the assignment: after depositing 1000 and
     * buying 5 shares @100, Ana has 500 cash and 5 shares. From there, a
     * 700 buy and an 8-share sell must both be rejected without changing
     * that state; only then does she sell 3 @120, ending at 860 cash / 2
     * shares.
     */
    public function test_full_scenario_from_the_assignment_story(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 1000);
        $this->buy($client, 'AAPL', 5, 100);

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '500.00']);
        $this->getJson("/api/clients/{$client->id}/holdings")
            ->assertJson(['holdings' => ['AAPL' => 5]]);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 7,
            'price_per_unit' => 100,
        ])->assertStatus(422);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'sell',
            'instrument' => 'AAPL',
            'quantity' => 8,
            'price_per_unit' => 120,
        ])->assertStatus(422);

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '500.00']);
        $this->getJson("/api/clients/{$client->id}/holdings")
            ->assertJson(['holdings' => ['AAPL' => 5]]);

        $this->sell($client, 'AAPL', 3, 120);

        $this->getJson("/api/clients/{$client->id}/balance")
            ->assertJson(['cash_balance' => '860.00']);
        $this->getJson("/api/clients/{$client->id}/holdings")
            ->assertJson(['holdings' => ['AAPL' => 2]]);
    }

    public function test_clients_are_isolated_from_each_other(): void
    {
        $ana = Client::create(['name' => 'Ana']);
        $marko = Client::create(['name' => 'Marko']);

        $this->deposit($ana, 1000);
        $this->buy($ana, 'AAPL', 5, 100);

        $this->getJson("/api/clients/{$marko->id}/balance")
            ->assertJson(['cash_balance' => '0.00']);
        $this->getJson("/api/clients/{$marko->id}/holdings")
            ->assertJson(['holdings' => []]);
    }

    public function test_amount_must_be_positive(): void
    {
        $client = Client::create(['name' => 'Ana']);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'deposit',
            'amount' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'deposit',
            'amount' => -100,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_quantity_must_be_a_positive_integer(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 1000);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'buy',
            'instrument' => 'AAPL',
            'quantity' => 2.5,
            'price_per_unit' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_buy_and_sell_require_an_instrument_quantity_and_price(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->deposit($client, 1000);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'buy',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['instrument', 'quantity', 'price_per_unit']);
    }

    public function test_unknown_transaction_type_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);

        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'transfer',
            'amount' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('type');
    }

    private function deposit(Client $client, float $amount): void
    {
        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'deposit',
            'amount' => $amount,
        ])->assertCreated();
    }

    private function buy(Client $client, string $instrument, int $quantity, float $price): void
    {
        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'buy',
            'instrument' => $instrument,
            'quantity' => $quantity,
            'price_per_unit' => $price,
        ])->assertCreated();
    }

    private function sell(Client $client, string $instrument, int $quantity, float $price): void
    {
        $this->postJson("/api/clients/{$client->id}/transactions", [
            'type' => 'sell',
            'instrument' => $instrument,
            'quantity' => $quantity,
            'price_per_unit' => $price,
        ])->assertCreated();
    }
}
