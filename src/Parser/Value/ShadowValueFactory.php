<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\ColorValue;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\ShadowLayer;
use Penyaskito\Dtcg\Tom\Value\ShadowValue;

final class ShadowValueFactory implements ValueFactory
{
    use CompositeFieldTrait;

    private const REQUIRED_KEYS = ['color', 'offsetX', 'offsetY', 'blur', 'spread'];

    private const ALLOWED_KEYS = ['color', 'offsetX', 'offsetY', 'blur', 'spread', 'inset'];

    private readonly ColorValueFactory $colorFactory;

    private readonly DimensionValueFactory $dimensionFactory;

    public function __construct()
    {
        $this->colorFactory = new ColorValueFactory();
        $this->dimensionFactory = new DimensionValueFactory();
    }

    public function type(): Type
    {
        return Type::Shadow;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        // Array form: list of shadow objects.
        if (is_array($raw) && array_is_list($raw) && $raw !== []) {
            $layers = [];
            foreach ($raw as $i => $item) {
                $layers[] = $this->readLayer($item, $pointer . '/' . $i);
            }

            return new ShadowValue($layers);
        }

        // Single-object form.
        return new ShadowValue([$this->readLayer($raw, $pointer)]);
    }

    private function readLayer(mixed $raw, string $pointer): ShadowLayer
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'shadow object must be a non-empty object');
        }

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown shadow property '%s'", (string) $key),
                );
            }
        }

        foreach (self::REQUIRED_KEYS as $required) {
            if (!array_key_exists($required, $raw)) {
                throw ParseError::at(
                    $pointer,
                    sprintf('shadow.%s is required', $required),
                );
            }
        }

        $color = $this->tryReadReference($raw['color'], $pointer . '/color')
            ?? $this->colorFactory->create($raw['color'], $pointer . '/color');
        $offsetX = $this->tryReadReference($raw['offsetX'], $pointer . '/offsetX')
            ?? $this->dimensionFactory->create($raw['offsetX'], $pointer . '/offsetX');
        $offsetY = $this->tryReadReference($raw['offsetY'], $pointer . '/offsetY')
            ?? $this->dimensionFactory->create($raw['offsetY'], $pointer . '/offsetY');
        $blur = $this->tryReadReference($raw['blur'], $pointer . '/blur')
            ?? $this->dimensionFactory->create($raw['blur'], $pointer . '/blur');
        $spread = $this->tryReadReference($raw['spread'], $pointer . '/spread')
            ?? $this->dimensionFactory->create($raw['spread'], $pointer . '/spread');

        \assert($color instanceof ColorValue || $color instanceof ReferenceValue);
        \assert($offsetX instanceof DimensionValue || $offsetX instanceof ReferenceValue);
        \assert($offsetY instanceof DimensionValue || $offsetY instanceof ReferenceValue);
        \assert($blur instanceof DimensionValue || $blur instanceof ReferenceValue);
        \assert($spread instanceof DimensionValue || $spread instanceof ReferenceValue);

        $inset = false;
        if (array_key_exists('inset', $raw)) {
            $insetRef = $this->tryReadReference($raw['inset'], $pointer . '/inset');
            $inset = match (true) {
                $insetRef !== null => $insetRef,
                is_bool($raw['inset']) => $raw['inset'],
                default => throw ParseError::at($pointer, 'shadow.inset must be a boolean'),
            };
        }

        return new ShadowLayer($color, $offsetX, $offsetY, $blur, $spread, $inset);
    }
}
