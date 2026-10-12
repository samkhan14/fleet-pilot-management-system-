<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Carbon\CarbonImmutable;

final class Clocks
{
    /**
     * A value with an offset is an absolute instant. A value without one is
     * organization-local wall time, which is what datetime-local inputs send.
     */
    public static function at(string $value, string $timezone): CarbonImmutable
    {
        if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', trim($value)) === 1) {
            return CarbonImmutable::parse($value)->utc();
        }

        return CarbonImmutable::parse($value, $timezone)->utc();
    }
}
