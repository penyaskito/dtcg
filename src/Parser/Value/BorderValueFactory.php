<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\BorderValue;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\StrokeStyleValue;

final class BorderValueFactory implements ValueFactory
{
    use CompositeFieldTrait;

    private const ALLOWED_KEYS = ['color', 'width', 'style'];

    private readonly ColorValueFactory $colorFactory;

    private readonly DimensionValueFactory $dimensionFactory;

    private readonly StrokeStyleValueFactory $strokeStyleFactory;

    public function __construct()
    {
        $this->colorFactory = new ColorValueFactory();
        $this->dimensionFactory = new DimensionValueFactory();
        $this->strokeStyleFactory = new StrokeStyleValueFactory();
    }

    public function type(): Type
    {
        return Type::Border;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'border $value must be an object');
        }

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown border property '%s'", (string) $key),
                );
            }
        }

        foreach (self::ALLOWED_KEYS as $required) {
            if (!array_key_exists($required, $raw)) {
                throw ParseError::at(
                    $pointer,
                    sprintf('border $value.%s is required', $required),
                );
            }
        }

        $color = $this->tryReadReference($raw['color'], $pointer . '/color')
            ?? $this->colorFactory->create($raw['color'], $pointer . '/color');
        $width = $this->tryReadReference($raw['width'], $pointer . '/width')
            ?? $this->dimensionFactory->create($raw['width'], $pointer . '/width');
        $style = $this->tryReadReference($raw['style'], $pointer . '/style')
            ?? $this->strokeStyleFactory->create($raw['style'], $pointer . '/style');

        \assert($color instanceof ColorValue || $color instanceof ReferenceValue);
        \assert($width instanceof DimensionValue || $width instanceof ReferenceValue);
        \assert($style instanceof StrokeStyleValue || $style instanceof ReferenceValue);

        return new BorderValue($color, $width, $style);
    }
}
