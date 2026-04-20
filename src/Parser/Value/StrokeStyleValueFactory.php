<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\LineCap;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleKeyword;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;

final class StrokeStyleValueFactory implements ValueFactory
{
    use CompositeFieldTrait;

    private const ALLOWED_KEYS = ['dashArray', 'lineCap'];

    public function type(): Type
    {
        return Type::StrokeStyle;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (is_string($raw)) {
            $keyword = StrokeStyleKeyword::tryFrom($raw);
            if ($keyword === null) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown strokeStyle keyword '%s'", $raw),
                );
            }

            return StrokeStyleValue::keyword($keyword);
        }

        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at(
                $pointer,
                'strokeStyle $value must be a recognised keyword string or a {dashArray, lineCap} object',
            );
        }

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown strokeStyle property '%s'", (string) $key),
                );
            }
        }

        $dashArray = $this->readDashArray($raw, $pointer);
        $lineCap = $this->readLineCap($raw, $pointer);

        return StrokeStyleValue::composite($dashArray, $lineCap);
    }

    /**
     * @param array<array-key, mixed> $raw
     * @return list<DimensionValue|ReferenceValue>
     */
    private function readDashArray(array $raw, string $pointer): array
    {
        if (
            !array_key_exists('dashArray', $raw)
            || !is_array($raw['dashArray'])
            || !array_is_list($raw['dashArray'])
            || $raw['dashArray'] === []
        ) {
            throw ParseError::at(
                $pointer,
                'strokeStyle $value.dashArray must be a non-empty list of dimension objects',
            );
        }

        $dimensionFactory = new DimensionValueFactory();
        $result = [];
        foreach ($raw['dashArray'] as $i => $item) {
            $itemPointer = $pointer . '/dashArray/' . $i;
            $value = $this->tryReadReference($item, $itemPointer)
                ?? $dimensionFactory->create($item, $itemPointer);
            \assert($value instanceof DimensionValue || $value instanceof ReferenceValue);
            $result[] = $value;
        }

        return $result;
    }

    /** @param array<array-key, mixed> $raw */
    private function readLineCap(array $raw, string $pointer): LineCap
    {
        if (!array_key_exists('lineCap', $raw) || !is_string($raw['lineCap'])) {
            throw ParseError::at($pointer, 'strokeStyle $value.lineCap must be a string');
        }
        $cap = LineCap::tryFrom($raw['lineCap']);
        if ($cap === null) {
            throw ParseError::at(
                $pointer,
                sprintf("unknown strokeStyle lineCap '%s'", $raw['lineCap']),
            );
        }

        return $cap;
    }
}
