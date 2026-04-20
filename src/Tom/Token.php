<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

abstract readonly class Token
{
    public function __construct(
        public string $name,
        public Path $path,
        public Metadata $metadata,
        public SourceMap $sourceMap,
    ) {
    }

    abstract public function type(): ?Type;
}
