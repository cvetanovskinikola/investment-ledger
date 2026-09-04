<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Client;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolio)
    {
        //
    }

    public function index(Client $client): AnonymousResourceCollection
    {
        return TransactionResource::collection(
            $client->transactions()->latest('id')->get()
        );
    }

    public function store(StoreTransactionRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();

        $transaction = match (TransactionType::from($data['type'])) {
            TransactionType::Deposit => $this->portfolio->deposit($client, (string) $data['amount']),
            TransactionType::Withdrawal => $this->portfolio->withdraw($client, (string) $data['amount']),
            TransactionType::Buy => $this->portfolio->buy(
                $client, $data['instrument'], (int) $data['quantity'], (string) $data['price_per_unit']
            ),
            TransactionType::Sell => $this->portfolio->sell(
                $client, $data['instrument'], (int) $data['quantity'], (string) $data['price_per_unit']
            ),
        };

        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }
}
