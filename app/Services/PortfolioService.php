<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InsufficientHoldingsException;
use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Reconstructs a client's cash balance and holdings from their append-only
 * transaction ledger, and appends new transactions after checking the
 * business rules (no negative cash, no selling what you don't own).
 */
class PortfolioService
{
    private const SCALE = 4;

    public function cashBalance(Client $client): string
    {
        $balance = '0';

        foreach ($client->transactions()->get() as $transaction) {
            $balance = match ($transaction->type) {
                TransactionType::Deposit, TransactionType::Sell => bcadd($balance, $transaction->amount, self::SCALE),
                TransactionType::Withdrawal, TransactionType::Buy => bcsub($balance, $transaction->amount, self::SCALE),
            };
        }

        return bcadd($balance, '0', 2);
    }

    /**
     * @return array<string, int> ticker => quantity currently held
     */
    public function holdings(Client $client): array
    {
        $holdings = [];

        foreach ($client->transactions()->get() as $transaction) {
            if (! $transaction->type->isInstrumentMovement()) {
                continue;
            }

            $ticker = $transaction->instrument;
            $holdings[$ticker] ??= 0;
            $holdings[$ticker] += $transaction->type === TransactionType::Buy
                ? $transaction->quantity
                : -$transaction->quantity;
        }

        return array_filter($holdings, fn (int $quantity) => $quantity > 0);
    }

    public function deposit(Client $client, string $amount): Transaction
    {
        return DB::transaction(function () use ($client, $amount) {
            $client = $this->lock($client);

            return $client->transactions()->create([
                'type' => TransactionType::Deposit,
                'amount' => $amount,
            ]);
        });
    }

    public function withdraw(Client $client, string $amount): Transaction
    {
        return DB::transaction(function () use ($client, $amount) {
            $client = $this->lock($client);

            if (bccomp($this->cashBalance($client), $amount, self::SCALE) < 0) {
                throw new InsufficientFundsException(
                    "Client '{$client->name}' has insufficient cash to withdraw {$amount}."
                );
            }

            return $client->transactions()->create([
                'type' => TransactionType::Withdrawal,
                'amount' => $amount,
            ]);
        });
    }

    public function buy(Client $client, string $instrument, int $quantity, string $pricePerUnit): Transaction
    {
        return DB::transaction(function () use ($client, $instrument, $quantity, $pricePerUnit) {
            $client = $this->lock($client);
            $cost = bcmul((string) $quantity, $pricePerUnit, self::SCALE);

            if (bccomp($this->cashBalance($client), $cost, self::SCALE) < 0) {
                throw new InsufficientFundsException(
                    "Client '{$client->name}' has insufficient cash to buy {$quantity} of {$instrument}."
                );
            }

            return $client->transactions()->create([
                'type' => TransactionType::Buy,
                'amount' => $cost,
                'instrument' => strtoupper($instrument),
                'quantity' => $quantity,
                'price_per_unit' => $pricePerUnit,
            ]);
        });
    }

    public function sell(Client $client, string $instrument, int $quantity, string $pricePerUnit): Transaction
    {
        return DB::transaction(function () use ($client, $instrument, $quantity, $pricePerUnit) {
            $client = $this->lock($client);
            $instrument = strtoupper($instrument);
            $owned = $this->holdings($client)[$instrument] ?? 0;

            if ($quantity > $owned) {
                throw new InsufficientHoldingsException(
                    "Client '{$client->name}' only owns {$owned} of {$instrument}, cannot sell {$quantity}."
                );
            }

            $proceeds = bcmul((string) $quantity, $pricePerUnit, self::SCALE);

            return $client->transactions()->create([
                'type' => TransactionType::Sell,
                'amount' => $proceeds,
                'instrument' => $instrument,
                'quantity' => $quantity,
                'price_per_unit' => $pricePerUnit,
            ]);
        });
    }

    /**
     * Re-fetch the client with a row lock so concurrent requests for the
     * same client are serialized while their balance/holdings are checked.
     */
    private function lock(Client $client): Client
    {
        return Client::whereKey($client->getKey())->lockForUpdate()->firstOrFail();
    }
}
