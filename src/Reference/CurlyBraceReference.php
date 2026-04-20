<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

final readonly class CurlyBraceReference implements Reference
{
    private const SEGMENT_PATTERN = '/^[^${}.][^{}.]*$/';

    /** @param list<string> $segments */
    public function __construct(
        private string $original,
        private array $segments,
    ) {
    }

    public static function parse(string $raw): self
    {
        if ($raw === '' || $raw[0] !== '{' || $raw[strlen($raw) - 1] !== '}') {
            throw new InvalidReferenceException(
                sprintf("curly-brace reference must be wrapped in '{' and '}': '%s'", $raw),
            );
        }

        $inner = substr($raw, 1, -1);
        if ($inner === '') {
            throw new InvalidReferenceException(
                sprintf("curly-brace reference has empty path: '%s'", $raw),
            );
        }

        $segments = explode('.', $inner);
        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1) {
                throw new InvalidReferenceException(
                    sprintf("invalid segment '%s' in curly-brace reference '%s'", $segment, $raw),
                );
            }
        }

        return new self($raw, $segments);
    }

    public function original(): string
    {
        return $this->original;
    }

    public function segments(): array
    {
        return $this->segments;
    }

    public function toJsonPointer(): string
    {
        if ($this->segments === []) {
            return '';
        }

        return '/' . implode('/', array_map(self::escape(...), $this->segments));
    }

    private static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
