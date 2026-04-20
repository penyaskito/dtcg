<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

use RuntimeException;

final class CyclicReferenceException extends RuntimeException
{
    /** @param list<string> $trail */
    public function __construct(
        string $message,
        public readonly array $trail,
    ) {
        parent::__construct($message);
    }
}
