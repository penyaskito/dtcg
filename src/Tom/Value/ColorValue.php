<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class ColorValue implements Value
{
    /** @var list<float|null> null represents the 'none' keyword */
    public array $components;

    /**
     * @param list<float|null> $components null = "none"
     */
    public function __construct(
        public ColorSpace $colorSpace,
        array $components,
        public ?float $alpha = null,
        public ?string $hex = null,
    ) {
        $this->components = $components;
    }

    public function type(): Type
    {
        return Type::Color;
    }
}
