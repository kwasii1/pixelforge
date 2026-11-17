<?php

namespace App\Http\Resources;

use App\Models\CreditTransaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->type->label(),
            'type' => $this->type->value,
            'prompt' => $this->generation?->prompt,
            'generationId' => $this->generation_id,
            'amount' => [
                'exact' => Money::toDecimal($this->amount),
                'display' => Money::format($this->amount),
            ],
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
