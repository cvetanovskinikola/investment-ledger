<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'instrument' => $this->instrument,
            'quantity' => $this->quantity,
            'price_per_unit' => $this->price_per_unit,
            'created_at' => $this->created_at,
        ];
    }
}
