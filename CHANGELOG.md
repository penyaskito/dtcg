# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.

## [Unreleased]

Pre-1.0 development. Targets DTCG spec version 2025.10.

### Fixed

- `Parser` now accepts numeric-string token names (e.g. `{"size": {"2":
  ..., "4": ...}}`). PHP's `json_decode(..., true)` coerces such object
  keys to int; the parser previously rejected the resulting int keys
  with "group keys must be strings". They are now coerced back to
  string at the iteration boundary in `walkGroup`.

### Added

- `SpecVersion` enum with vendored DTCG schemas under `schemas/2025.10/`.
- Full Token Object Model (TOM) under `src/Tom/`: immutable `Document`,
  `Group`, `Token` (abstract), `ValueToken`, `ReferenceToken`, `Path`,
  `Metadata`, `SourceMap`, `Type`, `Value`, `Walker`.
- All 13 DTCG value types with matching factories:
  `dimension`, `number`, `duration`, `fontWeight`, `cubicBezier`,
  `fontFamily`, `color`, `strokeStyle`, `border`, `transition`, `shadow`,
  `gradient`, `typography`.
- `Parser` — JSON → TOM with strict `$type` inference, `$type`
  inheritance, `$ref` handling, and `ParseError` carrying a JSON Pointer
  to the offending location.
- Group `$extends` — eager materialization at parse time via
  `ExtendsMaterializer`. Extending group's own children shadow inherited
  ones; `Group::$inheritedFrom` records the origin group for each
  inherited child (preserved through multi-level chains). Cycles,
  missing targets, and token-as-target all throw `ParseError`.
- Curly-brace aliases at `$value` root (`"$value": "{other.token}"`) —
  parsed into a `ValueToken` whose `$value` is a
  `Tom\Value\ReferenceValue` wrapping the `Reference`.
  `Resolver::resolveChain` follows through.
- Property-level references in every composite value sub-field
  (`BorderValue::$color`, `ShadowLayer::$offsetX`, etc.). Both DTCG
  forms supported — curly-brace string and `{"$ref": "..."}` object.
  Composite Value field types are now `Primitive|ReferenceValue`
  unions. Factories share detection via
  `Parser\Value\CompositeFieldTrait`.
- `Reference\Materializer` — fully resolves a `Document` (or single
  `Token` / `Value`) into a concrete result with no remaining
  references anywhere, including deep inside composites. Strict:
  throws `MaterializationException` on broken / cyclic / type-mismatched
  targets.
- `Reference\ReferenceCollector` — static helper yielding every
  `Reference` in a `Token` or `Value` (including composite
  sub-fields). Used by both semantic rules and serializers.
- `AliasTargetExists` and `NoAliasCycles` now walk composite
  sub-fields via `ReferenceCollector`, catching broken or cyclic refs
  anywhere in a value, not just at the root.
- `DtcgJsonSerializer` round-trips property-level refs verbatim —
  curly-brace as string, JSON-pointer as `{"$ref": ...}` object.
- `CssCustomPropertiesSerializer` materializes per-token internally
  (via `Materializer`); tokens that fail materialization are skipped.
- The `Tom\Value` interface's `type()` return type is now `?Type` — the
  reference-value case returns `null`; non-reference value classes
  continue to return a non-null `Type`.
- Reference layer: `Reference` interface, `CurlyBraceReference`,
  `JsonPointerReference`, `ReferenceParser`, `Resolver` (single-hop +
  chain-follow), `CycleDetector`, dedicated exceptions.
- `SchemaValidator` — structural validation via
  `justinrainbow/json-schema` (Draft-07 strict mode).
- `SemanticValidator` with a pluggable `Rule` architecture. Ships four
  rules: `AliasTargetExists`, `NoAliasCycles`, `TypeResolvable`,
  `NoExtendsCycles`.
- `Serializer` interface with two implementations:
  - `DtcgJsonSerializer` — TOM → DTCG-conforming JSON (round-trip is a
    fixed point at the TOM level).
  - `CssCustomPropertiesSerializer` — `@internal` reference
    implementation, demonstrates the `Serializer` interface.

### Not yet implemented

See [ROADMAP.md](ROADMAP.md) for the full list. Notable absences:
group `$root`, property-level JSON Pointer references that descend into
primitive-value internals (e.g. into a color's components array or a
cubic-bezier coordinate — `#/colors/blue/$value/components/0`), YAML
input.
