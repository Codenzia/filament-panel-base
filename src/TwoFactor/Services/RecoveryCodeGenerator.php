<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Services;

use Illuminate\Support\Str;

/**
 * Generates printable recovery codes (two 10-character alphanumeric halves
 * joined by a dash, ~119 bits of entropy). Codes are returned in plaintext so
 * the trait can show them to the user once and persist them hashed.
 */
class RecoveryCodeGenerator
{
    /**
     * @return array<int, string>
     */
    public function generate(int $count): array
    {
        return array_map(
            fn (): string => $this->makeCode(),
            range(1, max(1, $count)),
        );
    }

    private function makeCode(): string
    {
        return Str::random(10).'-'.Str::random(10);
    }
}
