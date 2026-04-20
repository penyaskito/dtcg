<?php

declare(strict_types=1);

namespace Penyaskito\Dtcg\Reference;

interface Reference
{
    /**
     * The reference as written in source — e.g. "{colors.blue}" or "#/colors/blue/$value".
     */
    public function original(): string;

    /**
     * RFC 6901 JSON Pointer form (no leading "#"). For curly-brace references, this
     * is the token path — consumers that need the token's $value must append "/$value".
     *
     * @return list<string>
     */
    public function segments(): array;

    public function toJsonPointer(): string;
}
