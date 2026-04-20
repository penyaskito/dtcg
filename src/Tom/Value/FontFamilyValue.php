<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class FontFamilyValue implements Value
{
    /** @var list<string> */
    public array $families;

    /** @param list<string> $families */
    public function __construct(array $families)
    {
        $this->families = $families;
    }

    public function type(): Type
    {
        return Type::FontFamily;
    }

    public function primary(): string
    {
        return $this->families[0];
    }
}
