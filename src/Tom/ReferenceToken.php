<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

use Penyaskito\Dtcg\Reference\Reference;

final readonly class ReferenceToken extends Token
{
    public function __construct(
        string $name,
        Path $path,
        public ?Type $declaredType,
        public Reference $reference,
        Metadata $metadata,
        SourceMap $sourceMap,
    ) {
        parent::__construct($name, $path, $metadata, $sourceMap);
    }

    public function type(): ?Type
    {
        return $this->declaredType;
    }
}
