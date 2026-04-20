<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg;

enum SpecVersion: string
{
    case V2025_10 = '2025.10';

    public function schemaDir(): string
    {
        return dirname(__DIR__) . '/schemas/' . $this->value;
    }
}
