<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser;

use RuntimeException;
use Throwable;

final class ParseError extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $pointer = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function at(string $pointer, string $message, ?Throwable $previous = null): self
    {
        $location = $pointer === '' ? '<root>' : $pointer;

        return new self($message . ' (at ' . $location . ')', $pointer, $previous);
    }
}
