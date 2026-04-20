<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

final readonly class JsonPointerReference implements Reference
{
    /** @param list<string> $segments */
    public function __construct(
        private string $original,
        private array $segments,
    ) {
    }

    public static function parse(string $raw): self
    {
        if (!str_starts_with($raw, '#/') && $raw !== '#') {
            throw new InvalidReferenceException(
                sprintf("JSON pointer reference must start with '#' or '#/': '%s'", $raw),
            );
        }

        $pointer = substr($raw, 1);
        if ($pointer === '' || $pointer === '/') {
            return new self($raw, []);
        }

        $rawSegments = explode('/', substr($pointer, 1));
        $segments = [];
        foreach ($rawSegments as $segment) {
            $segments[] = str_replace(['~1', '~0'], ['/', '~'], $segment);
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
