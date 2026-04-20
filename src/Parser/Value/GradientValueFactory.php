<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\GradientStop;
use Penyaskito\Dtcg\Tom\Value\GradientValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;

final class GradientValueFactory implements ValueFactory
{
    use CompositeFieldTrait;

    private const ALLOWED_STOP_KEYS = ['color', 'position'];

    private readonly ColorValueFactory $colorFactory;

    public function __construct()
    {
        $this->colorFactory = new ColorValueFactory();
    }

    public function type(): Type
    {
        return Type::Gradient;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || !array_is_list($raw) || $raw === []) {
            throw ParseError::at($pointer, 'gradient $value must be a non-empty list of stops');
        }

        $stops = [];
        foreach ($raw as $i => $item) {
            $stops[] = $this->readStop($item, $pointer . '/' . $i);
        }

        return new GradientValue($stops);
    }

    private function readStop(mixed $raw, string $pointer): GradientStop
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'gradient stop must be an object');
        }

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::ALLOWED_STOP_KEYS, true)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown gradient stop property '%s'", (string) $key),
                );
            }
        }

        foreach (self::ALLOWED_STOP_KEYS as $required) {
            if (!array_key_exists($required, $raw)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("gradient stop.%s is required", $required),
                );
            }
        }

        $color = $this->tryReadReference($raw['color'], $pointer . '/color')
            ?? $this->colorFactory->create($raw['color'], $pointer . '/color');
        \assert($color instanceof ColorValue || $color instanceof ReferenceValue);

        $positionRef = $this->tryReadReference($raw['position'], $pointer . '/position');
        $position = match (true) {
            $positionRef !== null => $positionRef,
            is_int($raw['position']) || is_float($raw['position'])
                => $this->validatePositionRange((float) $raw['position'], $pointer),
            default => throw ParseError::at($pointer, 'gradient stop.position must be a number'),
        };

        return new GradientStop($color, $position);
    }

    private function validatePositionRange(float $position, string $pointer): float
    {
        if ($position < 0 || $position > 1) {
            throw ParseError::at($pointer, 'gradient stop.position must be between 0 and 1');
        }

        return $position;
    }
}
