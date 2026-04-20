<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

final readonly class ValueToken extends Token
{
    public function __construct(
        string $name,
        Path $path,
        public Type $type,
        public Value $value,
        Metadata $metadata,
        SourceMap $sourceMap,
    ) {
        parent::__construct($name, $path, $metadata, $sourceMap);
    }

    public function type(): Type
    {
        return $this->type;
    }
}
