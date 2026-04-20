<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\DimensionValue;
use Penyaskito\Dtcg\Tom\Value\FontFamilyValue;
use Penyaskito\Dtcg\Tom\Value\FontWeightValue;
use Penyaskito\Dtcg\Tom\Value\NumberValue;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;
use Penyaskito\Dtcg\Tom\Value\TypographyValue;

final class TypographyValueFactory implements ValueFactory
{
    use CompositeFieldTrait;

    private const ALLOWED_KEYS = [
        'fontFamily', 'fontSize', 'fontWeight', 'letterSpacing', 'lineHeight',
    ];

    private readonly FontFamilyValueFactory $fontFamilyFactory;

    private readonly DimensionValueFactory $dimensionFactory;

    private readonly FontWeightValueFactory $fontWeightFactory;

    private readonly NumberValueFactory $numberFactory;

    public function __construct()
    {
        $this->fontFamilyFactory = new FontFamilyValueFactory();
        $this->dimensionFactory = new DimensionValueFactory();
        $this->fontWeightFactory = new FontWeightValueFactory();
        $this->numberFactory = new NumberValueFactory();
    }

    public function type(): Type
    {
        return Type::Typography;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'typography $value must be an object');
        }

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown typography property '%s'", (string) $key),
                );
            }
        }

        foreach (self::ALLOWED_KEYS as $required) {
            if (!array_key_exists($required, $raw)) {
                throw ParseError::at(
                    $pointer,
                    sprintf('typography $value.%s is required', $required),
                );
            }
        }

        $fontFamily = $this->tryReadReference($raw['fontFamily'], $pointer . '/fontFamily')
            ?? $this->fontFamilyFactory->create($raw['fontFamily'], $pointer . '/fontFamily');
        $fontSize = $this->tryReadReference($raw['fontSize'], $pointer . '/fontSize')
            ?? $this->dimensionFactory->create($raw['fontSize'], $pointer . '/fontSize');
        $fontWeight = $this->tryReadReference($raw['fontWeight'], $pointer . '/fontWeight')
            ?? $this->fontWeightFactory->create($raw['fontWeight'], $pointer . '/fontWeight');
        $letterSpacing = $this->tryReadReference($raw['letterSpacing'], $pointer . '/letterSpacing')
            ?? $this->dimensionFactory->create($raw['letterSpacing'], $pointer . '/letterSpacing');
        $lineHeight = $this->tryReadReference($raw['lineHeight'], $pointer . '/lineHeight')
            ?? $this->numberFactory->create($raw['lineHeight'], $pointer . '/lineHeight');

        \assert($fontFamily instanceof FontFamilyValue || $fontFamily instanceof ReferenceValue);
        \assert($fontSize instanceof DimensionValue || $fontSize instanceof ReferenceValue);
        \assert($fontWeight instanceof FontWeightValue || $fontWeight instanceof ReferenceValue);
        \assert($letterSpacing instanceof DimensionValue || $letterSpacing instanceof ReferenceValue);
        \assert($lineHeight instanceof NumberValue || $lineHeight instanceof ReferenceValue);

        return new TypographyValue($fontFamily, $fontSize, $fontWeight, $letterSpacing, $lineHeight);
    }
}
