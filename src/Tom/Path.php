<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

final readonly class Path
{
    /** @var list<string> */
    public array $segments;

    /** @param list<string> $segments */
    public function __construct(array $segments)
    {
        $this->segments = $segments;
    }

    public static function root(): self
    {
        return new self([]);
    }

    public static function fromDots(string $dotted): self
    {
        if ($dotted === '') {
            return self::root();
        }

        return new self(explode('.', $dotted));
    }

    public function append(string $segment): self
    {
        return new self([...$this->segments, $segment]);
    }

    public function isRoot(): bool
    {
        return $this->segments === [];
    }

    public function last(): ?string
    {
        if ($this->segments === []) {
            return null;
        }

        return $this->segments[array_key_last($this->segments)];
    }

    public function toString(string $separator = '.'): string
    {
        return implode($separator, $this->segments);
    }
}
