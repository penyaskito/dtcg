<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Serializer;

use Penyaskito\Dtcg\Tom\Document;

interface Serializer
{
    public function serialize(Document $document): string;
}
