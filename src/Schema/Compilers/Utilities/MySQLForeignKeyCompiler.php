<?php

declare(strict_types=1);

namespace Hibla\SchemaManager\Schema\Compilers\Utilities;

class MySQLForeignKeyCompiler extends ForeignKeyCompiler
{
    public function __construct()
    {
        $this->columnDelimiter = '`, `';
        $this->openQuote = '`';
        $this->closeQuote = '`';
    }
}
