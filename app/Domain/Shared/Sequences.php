<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;

final class Sequences
{
    public function next(int $organizationId, string $name, string $prefix): string
    {
        $value = DB::transaction(function () use ($organizationId, $name): int {
            $row = DB::table('number_sequences')
                ->where('organization_id', $organizationId)
                ->where('name', $name)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('number_sequences')->insert([
                    'organization_id' => $organizationId,
                    'name' => $name,
                    'value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->value + 1;
            DB::table('number_sequences')->where('id', $row->id)->update([
                'value' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });

        return sprintf('%s-%06d', $prefix, $value);
    }
}
