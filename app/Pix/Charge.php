<?php

namespace App\Pix;

use Carbon\CarbonInterface;

final class Charge
{
    public function __construct(
        public readonly string $providerId,
        public readonly string $payload,
        public readonly CarbonInterface $expiresAt,
        public readonly array $raw = [],
    ) {
    }
}
