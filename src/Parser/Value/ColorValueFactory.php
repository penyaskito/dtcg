<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Parser\ValueFactory;
use Penyaskito\Dtcg\Tom\Type;
use Penyaskito\Dtcg\Tom\Value;
use Penyaskito\Dtcg\Tom\Value\ColorSpace;
use Penyaskito\Dtcg\Tom\Value\ColorValue;

final class ColorValueFactory implements ValueFactory
{
    private const ALLOWED_KEYS = ['colorSpace', 'components', 'alpha', 'hex'];

    public function type(): Type
    {
        return Type::Color;
    }

    public function create(mixed $raw, string $pointer): Value
    {
        if (!is_array($raw) || (array_is_list($raw) && $raw !== [])) {
            throw ParseError::at($pointer, 'color $value must be an object');
        }

        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                throw ParseError::at(
                    $pointer,
                    sprintf("unknown color property '%s'", (string) $key),
                );
            }
        }

        $colorSpace = $this->readColorSpace($raw, $pointer);
        $components = $this->readComponents($raw, $pointer);
        $alpha = $this->readAlpha($raw, $pointer);
        $hex = $this->readHex($raw, $pointer);

        return new ColorValue($colorSpace, $components, $alpha, $hex);
    }

    /** @param array<array-key, mixed> $raw */
    private function readColorSpace(array $raw, string $pointer): ColorSpace
    {
        if (!array_key_exists('colorSpace', $raw) || !is_string($raw['colorSpace'])) {
            throw ParseError::at($pointer, 'color $value.colorSpace must be a string');
        }
        $space = ColorSpace::tryFrom($raw['colorSpace']);
        if ($space === null) {
            throw ParseError::at(
                $pointer,
                sprintf("unknown color colorSpace '%s'", $raw['colorSpace']),
            );
        }

        return $space;
    }

    /**
     * @param array<array-key, mixed> $raw
     * @return list<float|null>
     */
    private function readComponents(array $raw, string $pointer): array
    {
        if (
            !array_key_exists('components', $raw)
            || !is_array($raw['components'])
            || !array_is_list($raw['components'])
        ) {
            throw ParseError::at($pointer, 'color $value.components must be a list');
        }

        $components = [];
        foreach ($raw['components'] as $i => $component) {
            if (is_int($component) || is_float($component)) {
                $components[] = (float) $component;
            } elseif ($component === 'none') {
                $components[] = null;
            } else {
                throw ParseError::at(
                    $pointer,
                    sprintf('color $value.components[%d] must be a number or \'none\'', $i),
                );
            }
        }

        return $components;
    }

    /** @param array<array-key, mixed> $raw */
    private function readAlpha(array $raw, string $pointer): ?float
    {
        if (!array_key_exists('alpha', $raw)) {
            return null;
        }
        $alpha = $raw['alpha'];
        if (!is_int($alpha) && !is_float($alpha)) {
            throw ParseError::at($pointer, 'color $value.alpha must be a number');
        }
        $alpha = (float) $alpha;
        if ($alpha < 0 || $alpha > 1) {
            throw ParseError::at($pointer, 'color $value.alpha must be between 0 and 1');
        }

        return $alpha;
    }

    /** @param array<array-key, mixed> $raw */
    private function readHex(array $raw, string $pointer): ?string
    {
        if (!array_key_exists('hex', $raw)) {
            return null;
        }
        $hex = $raw['hex'];
        if (!is_string($hex) || preg_match('/^#[0-9a-fA-F]{6}$/', $hex) !== 1) {
            throw ParseError::at(
                $pointer,
                'color $value.hex must be a 6-digit hex string (e.g. "#ff00ff")',
            );
        }

        return $hex;
    }
}
