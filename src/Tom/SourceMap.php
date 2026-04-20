<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

final readonly class SourceMap
{
    public function __construct(
        public ?string $uri,
        public string $pointer,
    ) {
    }

    public static function synthetic(string $pointer = ''): self
    {
        return new self(null, $pointer);
    }
}
