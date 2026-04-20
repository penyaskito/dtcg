<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Validator\Rule;

use Penyaskito\Dtcg\Reference\Resolver;
use Penyaskito\Dtcg\Tom\Document;

final readonly class Context
{
    public function __construct(
        public Document $document,
        public Resolver $resolver,
    ) {
    }
}
