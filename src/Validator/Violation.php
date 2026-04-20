<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator;

final readonly class Violation
{
    public function __construct(
        public ViolationSource $source,
        public string $path,
        public string $message,
        public string $constraint,
    ) {
    }
}
