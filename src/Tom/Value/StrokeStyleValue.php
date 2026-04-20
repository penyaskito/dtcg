<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom\Value;

use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;

final readonly class StrokeStyleValue implements Value
{
    /** @var list<DimensionValue|ReferenceValue>|null */
    public ?array $dashArray;

    /**
     * Use the static factory methods `keyword()` or `composite()` rather than this
     * constructor; it exists to keep the class readonly-constructible.
     *
     * @param list<DimensionValue|ReferenceValue>|null $dashArray
     */
    public function __construct(
        public ?StrokeStyleKeyword $keyword,
        ?array $dashArray,
        public ?LineCap $lineCap,
    ) {
        $this->dashArray = $dashArray;
        if ($keyword !== null) {
            \assert($dashArray === null && $lineCap === null);
        } else {
            \assert($dashArray !== null && $lineCap !== null);
        }
    }

    public static function keyword(StrokeStyleKeyword $keyword): self
    {
        return new self($keyword, null, null);
    }

    /** @param list<DimensionValue|ReferenceValue> $dashArray */
    public static function composite(array $dashArray, LineCap $lineCap): self
    {
        return new self(null, $dashArray, $lineCap);
    }

    public function isKeyword(): bool
    {
        return $this->keyword !== null;
    }

    public function type(): Type
    {
        return Type::StrokeStyle;
    }
}
