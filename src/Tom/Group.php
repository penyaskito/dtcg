<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Tom;

use Penyaskito\Dtcg\Reference\Reference;

final readonly class Group
{
    /** @var array<string, Token|Group> */
    public array $children;

    /**
     * Map from child name to the path of the group where that child was
     * originally declared. Populated by `$extends` materialization; empty
     * otherwise. A child name present here means the child is inherited
     * (not overridden); absent means the child is own-declared.
     *
     * @var array<string, Path>
     */
    public array $inheritedFrom;

    /**
     * @param array<string, Token|Group> $children
     * @param array<string, Path> $inheritedFrom
     */
    public function __construct(
        public string $name,
        public Path $path,
        public ?Type $defaultType,
        public Metadata $metadata,
        public SourceMap $sourceMap,
        array $children = [],
        public ?Reference $extendsFrom = null,
        array $inheritedFrom = [],
    ) {
        $this->children = $children;
        $this->inheritedFrom = $inheritedFrom;
    }

    public function child(string $name): Token|Group|null
    {
        return $this->children[$name] ?? null;
    }

    public function isRoot(): bool
    {
        return $this->path->isRoot();
    }
}
