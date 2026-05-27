<?php

declare(strict_types=1);

namespace Hibla\Migrations\Exceptions;

use Hibla\QueryBuilder\Exceptions\QueryBuilderException;

/**
 * Thrown when schema compiler encounters an error
 */
class SchemaCompilerException extends QueryBuilderException
{
}
