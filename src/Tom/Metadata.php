<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

final readonly class Metadata
{
    /** @param array<string, mixed> $extensions */
    public function __construct(
        public ?string $description = null,
        public array $extensions = [],
        public bool|string|null $deprecated = null,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }
}
