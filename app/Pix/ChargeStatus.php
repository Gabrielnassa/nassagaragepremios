<?php

namespace App\Pix;

use Carbon\CarbonInterface;

final class ChargeStatus
{
    public function __construct(
        public readonly string $providerId,
        public readonly bool $paid,
        public readonly ?CarbonInterface $paidAt = null,
    ) {
    }
}
