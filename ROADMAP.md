# Roadmap

State of the project and the remaining work. Detailed enough that a new
contributor (human or agent) can pick up without re-deriving context. Not
a wishlist — only items that are already committed-to in scope.

## Current status

- **Spec version supported:** DTCG 2025.10 (vendored under `schemas/2025.10/`).
- **PHP floor:** 8.3.
- **Tests:** 336 / 1057 assertions, phpstan max clean.
- **v1 progress:** core pipeline complete end-to-end — parse, validate
  (structural + semantic), resolve references, serialize to CSS custom
  properties. See *v1 remaining* below for the gaps.

## v1 — done

- `SpecVersion` enum.
- **TOM**: `Group`, abstract `Token`, `ValueToken`, `ReferenceToken`, `Path`,
  `Metadata`, `SourceMap`, `Type`, `Value` interface, `Document`, `Walker`.
- **All 13 value classes** (+ their enums where applicable): `dimension`,
  `number`, `duration`, `fontWeight`, `cubicBezier`, `fontFamily`, `color`,
  `strokeStyle`, `border`, `transition`, `shadow`, `gradient`, `typography`.
- **Parser**: JSON → TOM, `$type` inheritance, strict mode, `$ref`
  detection, informative `ParseError` (with `$pointer` property + JSON
  Pointer location in every message).
- **Reference layer**: `Reference` interface, `CurlyBraceReference`,
  `JsonPointerReference`, `ReferenceParser`, `Resolver` (single-hop +
  chain-follow), `CycleDetector`, `UnresolvableReferenceException`,
  `CyclicReferenceException`.
- **Structural validator**: `SchemaValidator` wrapping
  `justinrainbow/json-schema` against the vendored schemas (Draft-07
  strict mode, required for the schema's `if/then/else` branches to fire).
- **Semantic validator**: `SemanticValidator` with a `Rule` plugin arch.
  Ships four rules: `AliasTargetExists`, `NoAliasCycles`, `TypeResolvable`,
  `NoExtendsCycles`.
- **Group `$extends`**: eager materialization at parse time via
  `ExtendsMaterializer`. Extending group's own children shadow inherited
  ones. `Group::$inheritedFrom` records per-child origin path (preserved
  through multi-level chains). Cycles, missing targets, and token-as-target
  throw `ParseError` during parsing. `DtcgJsonSerializer` re-emits
  `$extends` and elides inherited children for clean round-trip.
- **Curly-brace aliases at `$value` root** (`"$value": "{other.token}"`):
  parsed into a `ValueToken` whose `$value` is a
  `Tom\Value\ReferenceValue` wrapping the parsed `Reference`. The
  `Value` interface's `type()` is now `?Type` so the reference-value
  case can return `null` (non-reference values still return non-null).
  Strict mode still requires `$type` own-or-inherited. `Resolver::resolveChain`
  follows through `ReferenceValue` in addition to `ReferenceToken`.
  Both `AliasTargetExists` and `NoAliasCycles` cover both forms.
- **Property-level references inside composite sub-fields**: every
  composite Value class (`Border`, `Transition`, `Shadow`, `Gradient`,
  `Typography`, and `StrokeStyle` composite-form) accepts either the
  primitive form or `ReferenceValue` per sub-field. Both DTCG reference
  forms supported — curly-brace string (`"{colors.primary}"`) and
  JSON-pointer object (`{"$ref": "#/colors/primary"}`). Factories
  dispatch via a shared `Parser\Value\CompositeFieldTrait`.
- **`Reference\Materializer`**: library utility that takes a
  `Document` and returns a new fully-resolved `Document` (no remaining
  `ReferenceToken` or `ReferenceValue` anywhere, including deep inside
  composite sub-fields). Strict — throws `MaterializationException` on
  broken / cyclic / type-mismatched targets. Used internally by
  `CssCustomPropertiesSerializer` (per-token, with skip-on-error
  fallback) and available to consumers as a first-class pre-serialize
  step.
- **`Reference\ReferenceCollector`**: static helper that yields every
  `Reference` contained in a `Token` or `Value`, including composite
  sub-fields. Shared by both semantic rules and serializers.
- **Serializers**:
  - `DtcgJsonSerializer` preserves references on output — curly-brace
    refs round-trip as strings, JSON-pointer refs as `{"$ref": "..."}`
    objects.
  - `CssCustomPropertiesSerializer` runs each token through
    `Materializer` internally before emitting CSS — individual tokens
    whose materialization fails are silently skipped.
- **Serializer**: `Serializer` interface + two implementations:
  - `CssCustomPropertiesSerializer` (marked `@internal` — reference
    implementation only).
  - `DtcgJsonSerializer` — TOM → DTCG-conforming JSON. Round-trip is a
    fixed point at the TOM level. Canonical forms for ambiguous input
    shapes (single-family fontFamily → string; single-layer shadow →
    object). See *Deferred* below for the one lossy case (`$root`).
- **Docs**: `README.md` (install, quick-start examples, architecture,
  known quirks), `LICENSE` (MIT), `CHANGELOG.md` (Keep-a-Changelog
  format, pre-1.0 `[Unreleased]`).

## v1 — remaining

### `Io/` extraction

File loading and JSON decoding are currently inlined in `Parser::parseFile`
and `SchemaValidator::validateFile`. The plan groups them:

- `Io\Source` — `{uri, contents}` value object.
- `Io\FileLoader` — reads a path, returns `Source`.
- `Io\JsonDecoder` — `Source` → decoded array/object, throws on bad JSON.
- `Io\YamlDecoder` — optional, uses `symfony/yaml` if installed (already in
  `require-dev` and suggested). Lets the Parser accept `.tokens.yml`.

No functional regression expected; this is tidying so YAML input lands
cleanly and both validators share one decode path.

## Deferred within v1 — with reason

Both are real DTCG features but have deliberate deferral reasons:

### Property-level JSON Pointer references into primitive-value internals

Pointers that descend past a token's `$value` into a primitive's internal
structure — e.g. `#/colors/blue/$value/components/0` (the `0`-th RGB
component), `#/easings/inOut/$value/2` (a cubic-bezier coordinate),
`#/shadows/card/$value/inset` (the boolean flag on a shadow layer).

**Status:** `Resolver::resolve()` throws
`UnresolvableReferenceException` with "property-level references not yet
supported" the moment a segment begins with `$`. `Materializer` surfaces
the same error in its messages.

**Why deferred:** the Resolver would have to descend from the TOM (a
tree of `Token`/`Group`/`Value` objects) into each `Value` class's
internal structure — different for every type (color components,
cubic-bezier coordinates, font-family list indices, shadow inset bools,
etc.). Each leaf needs a custom walk step. Non-trivial and rarely
needed.

**Workaround today:** for composite *sub-fields* (color, width, style,
etc.) use the top-level reference form — `{colors.primary}` or
`{"$ref": "#/colors/primary"}` — which is fully supported. For
internal-of-primitive references, duplicate the value inline.

### Group `$root` token

DTCG lets a group declare a `$root` property whose value is a Token
representing the group's "primary" or default value (typically used so
that consumers can treat the group itself as referenceable via its
`$root`).

**Status:** `Parser` silently drops any `$root` key today (the generic
"keys starting with `$` are metadata, skip" branch swallows it). The
TOM has no representation for it. `DtcgJsonSerializer` therefore cannot
round-trip a `$root` — the one lossy case in an otherwise fixed-point
round-trip.

**Why deferred:** `Group` has no slot to hold a `$root` Token, and the
semantics (how does a `$root` interact with `$extends`, with inherited
`$type`, with being referenced?) want a dedicated session. Low-priority
until a real consumer hits it.

**Workaround today:** place the primary value as a named child token
alongside the siblings.

## v2 — scope (not started)

Full `.resolver.json` support.

- **ROM** (Resolver Object Model) under `src/Resolver/Rom/`:
  `Document`, `Set`, `Modifier`, `ResolutionOrder`, `Context`.
- `Resolver\Parser` — `.resolver.json` → ROM.
- `Resolver\Evaluator` — `(ROM, ContextSelection) → Tom\Document`.
- `Resolver\ContextSelection` — user-supplied modifier selection.
- `Resolver\Merger` — last-write-wins at three levels (resolutionOrder,
  set.sources, modifier.context.sources). Per the design doc: *write once,
  test hard.*
- Reference rules enforced post-resolution:
  - Sets cannot reference modifiers.
  - Modifiers cannot reference modifiers.
  - Nothing references `resolutionOrder`.

ROM depends on TOM; TOM stays oblivious to ROM.

Naming note: the project doc left open whether to rename `Rom/` to a
more descriptive directory (e.g. `Resolver\Model\`). Decide before the
first commit in v2 — renaming later is cheap but easier not to do twice.

## v3 — noted, not committed

Ideas parked from reference-implementation surveys (e.g. `@terrazzo/parser`
comparison). Not in scope for v1 or v2; kept here so they aren't
re-derived each time someone reads the code.

- **Accessibility lint rules** (e.g. `a11y-min-contrast`). Fit the
  existing `Rule` plugin arch cleanly — a future `ContrastRule` would
  compare color-pair tokens against WCAG thresholds. Out of scope until
  the core is stable.

## Explicitly out of scope

Not being built in this package (per original design doc):

- **CLI** — consumer's job; separate package if needed.
- **Transforms / "platforms" à la Style Dictionary** — the
  `CssCustomPropertiesSerializer` we ship is a reference impl, not a
  pluggable transform framework.
- **File discovery** — `Parser::parseFile` takes an absolute path;
  consumers decide what to load.
- **Caching** — TOM is immutable, so consumers can cache `Document`
  instances trivially. No in-library cache.
- **Drupal dependencies** — this library must stay Drupal-free.

## Architectural decisions worth preserving

One-line rationale each. If you find yourself questioning any of these,
check the rationale before changing — a couple were non-obvious.

- **`Tom\Document` wraps the root `Group`.** Spec version and source URI
  are document-level, not per-node. Later the resolver (v2) needs a
  natural home for "this tree came from N inputs" metadata.
- **TOM nodes are `readonly`.** Immutable tree = trivially cacheable,
  safe to share across threads / requests. Editing produces a new tree.
- **Reference layer is independent from `Value`.** `ReferenceToken`
  replaces `ValueToken` at the Token level; there is *no*
  `ReferenceValue implements Value` yet. See the deferred items above.
- **Strict `$type` inference, by default.** Tokens without `$type` (own
  or inherited) are rejected at parse time. The schema's inference
  allowance is ignored. Reason: the prose forbids it; strictness
  surfaces real mistakes sooner.
- **`SchemaValidator` is structural only.** The DTCG schema can't check
  `$value` shape when `$type` is inherited from an ancestor group (its
  `if/then` branches trigger only on a token with `$type` directly on
  it). Shape checks happen at parse time in the per-type value
  factories — the `Parser` resolves `$type` through inheritance before
  invoking a factory, so bad shapes under an ancestor-inherited `$type`
  throw `ParseError`. Cross-token semantic checks (alias targets,
  cycles, type resolvability) live in the `Rule` plugin arch. See
  memory note "DTCG schema $value checks require direct $type".
- **`CssCustomPropertiesSerializer` is `@internal`.** Shipped as a
  reference implementation + end-to-end test harness. Real consumers
  (with naming schemes, theming, prefixes) must ship their own
  `Serializer`.
- **`ParseError` uses `::at($pointer, $message)` for every thrown error,**
  producing `"message (at /path)"` + an exposed `pointer` property.
  Every expectExceptionMessage assertion in the test suite includes the
  location.
- **`justinrainbow/json-schema` for JSON Schema,** not `opis/json-schema`.
  Confirmed preference.
- **`\assert()` for invariant narrowing,** not defensive
  `if (!$x instanceof Foo) continue;` branches. See memory note
  "Use \assert for PHPStan narrowing".
- **Single-quoted strings for `ParseError` messages** that reference
  DTCG property names (`$value`, `$type`, `$ref`, …). Double-quotes
  silently interpolate the PHP local. Recurring bug — memorialised.
- **Numeric-string token names are coerced back to string in `walkGroup`.**
  PHP's `json_decode(..., true)` turns object keys like `"2"` into int
  `2`. Real DTCG fixtures (e.g. numeric scales `"size": {"2": ..., "4":
  ...}`) hit this. The parser silently casts at the iteration boundary;
  do not re-introduce a "keys must be strings" check there.
- **Happy-path tests also assert `$value->type()`.** Factories do not
  have standalone `testTypeReturnsX` tests; those are implicit via
  `Parser` using `$factory->type()->value` to key the registry.
- **`$extends` inherits children only, not group metadata or `$type`.**
  Extending groups must declare their own `$type` if they want one. The
  spec says extends inherits "tokens and properties" but is vague on
  which properties — inheriting metadata would produce surprising
  results (copying `$description` across groups). Children-only keeps
  semantics predictable. If a concrete consumer needs metadata
  inheritance, revisit.
- **`$extends` is materialized eagerly by `Parser`, not lazily.** Design
  decision 7. The parsed `Document` has merged children ready to use;
  `Group::$extendsFrom` remains set as a marker (used by
  `DtcgJsonSerializer` to re-emit `$extends` and by `NoExtendsCycles`).
  `Group::$inheritedFrom` records the origin group path per inherited
  child (preserving original declaration point through multi-level
  chains, not just the immediate parent).

## Resolved open questions

Left here so they don't come back up.

- *Should TOM root be wrapped in `Document` or exposed as `Group`?* →
  **Document wrapper.** (Spec version + v2 resolver output both need a
  document-level home.)
- *Where does `CssCustomPropertiesSerializer` live?* → **In core,
  marked `@internal` as a reference impl.** Can be extracted to
  `penyaskito/dtcg-css` later if design pressure grows (naming schemes,
  theming, etc.).
