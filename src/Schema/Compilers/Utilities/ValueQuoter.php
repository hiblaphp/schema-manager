<?php

declare(strict_types=1);

namespace Hibla\Migrations\Schema\Compilers\Utilities;

/**
 * Handles string quoting and escaping for DDL compilation
 */
class ValueQuoter
{
    public function quote(string $value): string
    {
        return $this->escapeAndQuote($value);
    }

    protected function escapeAndQuote(string $value): string
    {
        $escaped = str_replace("'", "''", $value);

        return "'{$escaped}'";
    }
}