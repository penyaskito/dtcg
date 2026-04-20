<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

final class ReferenceParser
{
    public static function parse(string $raw): Reference
    {
        if ($raw === '') {
            throw new InvalidReferenceException('reference is empty');
        }

        return match ($raw[0]) {
            '{' => CurlyBraceReference::parse($raw),
            '#' => JsonPointerReference::parse($raw),
            default => throw new InvalidReferenceException(
                sprintf("unrecognized reference syntax: '%s'", $raw),
            ),
        };
    }

    public static function looksLikeReference(string $raw): bool
    {
        return $raw !== '' && ($raw[0] === '{' || $raw[0] === '#');
    }
}
