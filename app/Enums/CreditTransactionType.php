<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    /** Credits bought by the user. */
    case Purchase = 'purchase';

    /** The most a generation can cost, set aside when it is submitted. */
    case Hold = 'hold';

    /** A hold given back, when a generation finishes or fails. */
    case Release = 'release';

    /** What a succeeded generation actually cost the user. */
    case Charge = 'charge';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Credits purchased',
            self::Hold => 'Reserved for generation',
            self::Release => 'Reservation returned',
            self::Charge => 'Generation charged',
        };
    }
}
