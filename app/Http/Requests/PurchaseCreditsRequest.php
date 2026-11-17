<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseCreditsRequest extends FormRequest
{
    /**
     * The credit packs on offer, in whole dollars.
     */
    public const PACKS = [5, 10, 25];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dollars' => ['required', 'integer', Rule::in(self::PACKS)],
        ];
    }

    public function nanodollars(): int
    {
        return $this->integer('dollars') * Money::NANODOLLARS_PER_DOLLAR;
    }
}
