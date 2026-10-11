<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use RuntimeException;

final class Payloads
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hash(array $payload): string
    {
        $encoded = json_encode($payload);
        if ($encoded === false) {
            throw new RuntimeException('Could not encode the request payload.');
        }

        return hash('sha256', $encoded);
    }
}
