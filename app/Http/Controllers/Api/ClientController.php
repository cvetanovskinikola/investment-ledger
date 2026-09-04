<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolio)
    {
        //
    }

    public function index(): AnonymousResourceCollection
    {
        return ClientResource::collection(Client::orderBy('name')->get());
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client);
    }

    public function balance(Client $client): JsonResponse
    {
        return response()->json([
            'client_id' => $client->id,
            'cash_balance' => $this->portfolio->cashBalance($client),
        ]);
    }

    public function holdings(Client $client): JsonResponse
    {
        return response()->json([
            'client_id' => $client->id,
            'holdings' => $this->portfolio->holdings($client),
        ]);
    }
}
