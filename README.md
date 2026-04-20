# penyaskito/dtcg

[![CI](https://github.com/penyaskito/dtcg/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/penyaskito/dtcg/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/penyaskito/dtcg/graph/badge.svg?token=5IDDXZ2J3M)](https://codecov.io/gh/penyaskito/dtcg)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](composer.json)
[![License: MIT](https://img.shields.io/badge/license-MIT-yellow.svg)](LICENSE)

A PHP library for parsing, validating, resolving, and serializing
[Design Tokens Community Group (DTCG)](https://www.designtokens.org/)
format documents. Targets DTCG spec version **2025.10**.

## Status

Pre-1.0. The public API surface is stabilising but may still shift
before `1.0.0` is tagged. See [ROADMAP.md](ROADMAP.md) for what's in
scope, what's deferred, and why.

## Requirements

- PHP **8.3** or newer
- Composer

## Install

```bash
composer require penyaskito/dtcg
```

Optional: `symfony/yaml` if you want YAML input support (not yet
wired — tracked in the roadmap).

## Quick start

### Parse a `.tokens.json` file

```php
use Penyaskito\Dtcg\Parser\Parser;

$parser = new Parser();
$document = $parser->parseFile('/path/to/design.tokens.json');

// $document is a Penyaskito\Dtcg\Tom\Document wrapping the TOM root.
$spacingBase = $document->root->child('spacing')?->child('base');
```

### Structural validation (JSON Schema)

```php
use Penyaskito\Dtcg\Validator\SchemaValidator;

$validator = new SchemaValidator();
$violations = $validator->validateFile('/path/to/design.tokens.json');

foreach ($violations as $v) {
    printf("[%s] %s: %s\n", $v->source->value, $v->path, $v->message);
}
```

### Semantic validation (alias targets, cycles, type resolvability)

```php
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Validator\SemanticValidator;

$document = (new Parser())->parseFile('/path/to/design.tokens.json');
$violations = (new SemanticValidator())->validate($document);

foreach ($violations as $v) {
    printf("[%s] %s: %s\n", $v->source->value, $v->path, $v->message);
}
```

Plug in your own rule by implementing `Penyaskito\Dtcg\Validator\Rule\Rule`
and passing a custom rule list to the validator constructor.

### Resolve references

```php
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\ReferenceParser;
use Penyaskito\Dtcg\Reference\Resolver;

$document = (new Parser())->parseFile('/path/to/design.tokens.json');

$resolver = new Resolver($document);
$reference = ReferenceParser::parse('#/spacing/base');
$target = $resolver->resolveChain($reference); // follows intermediate ReferenceTokens
```

### Materialize a fully-resolved document

`Materializer` returns a new `Document` with every reference resolved
end-to-end — `ReferenceToken`s become `ValueToken`s, and references inside
composite sub-fields (e.g. `border.color`, `gradient.stops[].color`) are
expanded to concrete values. Strict: throws `MaterializationException` on
broken, cyclic, or type-mismatched targets.

```php
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Reference\Materializer;
use Penyaskito\Dtcg\Reference\Resolver;

$document = (new Parser())->parseFile('/path/to/design.tokens.json');
$materialized = (new Materializer(new Resolver($document)))->materialize($document);
```

Use this when downstream code needs concrete values and shouldn't have
to walk references itself (theme emitters, contrast checkers, etc.). The
input document is left unchanged.

### Serialize back to DTCG JSON

```php
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Serializer\DtcgJsonSerializer;

$document = (new Parser())->parseFile('/path/to/design.tokens.json');
$json = (new DtcgJsonSerializer())->serialize($document);
```

Parse → serialize → parse is a fixed point: the re-parsed TOM is
equivalent to the original (see *Known quirks* below for the one
exception).

### Serialize to CSS custom properties *(reference implementation only)*

```php
use Penyaskito\Dtcg\Parser\Parser;
use Penyaskito\Dtcg\Serializer\CssCustomPropertiesSerializer;

$document = (new Parser())->parseFile('/path/to/design.tokens.json');
$css = (new CssCustomPropertiesSerializer())->serialize($document);
```

This serializer is marked `@internal`. It exists to demonstrate the
`Serializer` interface and to exercise the pipeline end-to-end. Real
consumers (with naming schemes, theming, prefixes) should ship their
own `Serializer` implementation. See the docblock on the class for
details.

## Architecture

Two object models, one-way dependency:

- **TOM** (`Penyaskito\Dtcg\Tom`): immutable representation of a parsed
  `.tokens.json`. `Document` wraps the root `Group`; groups contain
  `Group` and `Token` children. `Token` is either a `ValueToken` (has a
  typed `$value`) or a `ReferenceToken` (has a `$ref`).
- **ROM** (`Penyaskito\Dtcg\Resolver\Rom`): *not yet implemented.* Will
  represent a parsed `.resolver.json`. ROM depends on TOM; TOM stays
  oblivious to ROM.

The main surfaces:

| Component | Purpose |
| --- | --- |
| `Parser` | JSON array → `Tom\Document`, with strict `$type` inference |
| `Reference\Resolver` | Walk a `Reference` against a TOM, optionally chain-follow |
| `Reference\Materializer` | Resolve every reference (incl. inside composites) into a new `Document` |
| `Validator\SchemaValidator` | Structural validation against the vendored DTCG schemas |
| `Validator\SemanticValidator` | Rule-based semantic validation (alias targets, cycles, etc.) |
| `Serializer\DtcgJsonSerializer` | TOM → DTCG JSON (round-trip safe) |
| `Serializer\CssCustomPropertiesSerializer` | `@internal` reference impl |

All TOM nodes are `readonly`. Editing a TOM means building a new one.
Every node carries a `SourceMap` (URI + RFC 6901 JSON pointer into the
source) for error reporting.

## Error handling

`ParseError` messages always include the JSON Pointer location of the
offending element:

```
dimension $value.unit must be a string (at /spacing/base/$value)
```

The `ParseError::$pointer` property (readonly, public) also exposes the
pointer as a string for programmatic access.

## Known quirks

- **Structural validation does not catch bad `$value` shapes when
  `$type` is inherited from an ancestor group.** The DTCG schema's
  per-type `$value` branches are gated on `$type` being *directly* on
  the token. When it's inherited, the schema has no way to know the
  token's type. `SemanticValidator` does not yet close this gap either
  — run the `Parser` (which resolves `$type` through inheritance and
  invokes value factories) to catch shape errors.
- **`CssCustomPropertiesSerializer` silently skips composite-value
  nuances** it can't represent (e.g. composite `strokeStyle` falls
  back to `dashed`). It's a reference implementation, not a production
  emitter.
- **`$root`, property-level JSON Pointer references that descend into
  primitive-value internals (e.g. into a color's components array), and
  YAML input are not yet supported** — see [ROADMAP.md](ROADMAP.md) for
  details and workarounds. Property-level references at the
  *sub-field* level of a composite (e.g. `border.color` pointing to a
  color token) are fully supported.

## Contributing

Development setup uses [DDEV](https://ddev.com). Tests with PHPUnit and
static analysis with PHPStan at level `max`. The project also maintains:

- [ROADMAP.md](ROADMAP.md) — committed scope and architectural decisions
  worth preserving.
- [CLAUDE.md](CLAUDE.md) — instructions for coding agents (and humans)
  working in this repo, including commit-attribution conventions.

```bash
ddev start
ddev composer install
ddev exec vendor/bin/phpunit
ddev exec vendor/bin/phpstan analyse
```

## License

MIT. See [LICENSE](LICENSE).
