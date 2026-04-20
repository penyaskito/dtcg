<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

use Penyaskito\Dtcg\SpecVersion;

final readonly class Document
{
    public function __construct(
        public SpecVersion $specVersion,
        public Group $root,
        public ?string $sourceUri = null,
    ) {
    }
}
