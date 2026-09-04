<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cashTypes = [TransactionType::Deposit->value, TransactionType::Withdrawal->value];
        $instrumentTypes = [TransactionType::Buy->value, TransactionType::Sell->value];

        return [
            'type' => ['required', Rule::in(array_column(TransactionType::cases(), 'value'))],

            'amount' => [
                'required_if:type,'.implode(',', $cashTypes),
                'prohibited_unless:type,'.implode(',', $cashTypes),
                'numeric',
                'min:0.01',
            ],

            'instrument' => [
                'required_if:type,'.implode(',', $instrumentTypes),
                'prohibited_unless:type,'.implode(',', $instrumentTypes),
                'string',
                'max:20',
            ],

            'quantity' => [
                'required_if:type,'.implode(',', $instrumentTypes),
                'prohibited_unless:type,'.implode(',', $instrumentTypes),
                'integer',
                'min:1',
            ],

            'price_per_unit' => [
                'required_if:type,'.implode(',', $instrumentTypes),
                'prohibited_unless:type,'.implode(',', $instrumentTypes),
                'numeric',
                'min:0.01',
            ],
        ];
    }
}
