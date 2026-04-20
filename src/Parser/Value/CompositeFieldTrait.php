<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Parser\Value;

use Penyaskito\Dtcg\Parser\ParseError;
use Penyaskito\Dtcg\Reference\CurlyBraceReference;
use Penyaskito\Dtcg\Reference\InvalidReferenceException;
use Penyaskito\Dtcg\Reference\JsonPointerReference;
use Penyaskito\Dtcg\Tom\Value\ReferenceValue;

/**
 * Shared detection of a DTCG `tokenValueReference` form at a composite
 * value's sub-field position. Two valid forms per spec:
 *
 *   - Curly-brace reference: the raw is a string starting with `{`.
 *     Example: `"{colors.primary}"`.
 *   - JSON-pointer reference object: the raw is `{"$ref": "#/..."}` with
 *     exactly that one key.
 *
 * Any other shape returns `null`, letting the caller fall back to the
 * primitive factory for that field. Both forms throw `ParseError` with
 * the JSON-Pointer location on malformed input.
 */
trait CompositeFieldTrait
{
    private function tryReadReference(mixed $raw, string $pointer): ?ReferenceValue
    {
        if (is_string($raw) && $raw !== '' && $raw[0] === '{') {
            try {
                return new ReferenceValue(CurlyBraceReference::parse($raw));
            } catch (InvalidReferenceException $e) {
                throw ParseError::at(
                    $pointer,
                    sprintf('invalid curly-brace reference: %s', $e->getMessage()),
                );
            }
        }

        if (is_array($raw) && array_keys($raw) === ['$ref']) {
            $target = $raw['$ref'];
            if (!is_string($target)) {
                throw ParseError::at($pointer, '$ref must be a string');
            }
            try {
                return new ReferenceValue(JsonPointerReference::parse($target));
            } catch (InvalidReferenceException $e) {
                throw ParseError::at($pointer, sprintf('invalid $ref: %s', $e->getMessage()));
            }
        }

        return null;
    }
}
