<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

use RuntimeException;

final class UnresolvableReferenceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly Reference $reference,
    ) {
        parent::__construct($message);
    }
}
