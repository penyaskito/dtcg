<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

use Penyaskito\Dtcg\Tom\Path;

final class CycleDetector
{
    /** @var array<string, true> */
    private array $visited = [];

    /** @var list<string> */
    private array $order = [];

    public function visit(Path $path): void
    {
        $key = $path->toString();
        if (isset($this->visited[$key])) {
            $trail = [...$this->order, $key];
            throw new CyclicReferenceException(
                sprintf('reference cycle detected: %s', implode(' -> ', $trail)),
                $trail,
            );
        }
        $this->visited[$key] = true;
        $this->order[] = $key;
    }
}
