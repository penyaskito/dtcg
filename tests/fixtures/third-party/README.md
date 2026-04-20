# Third-party fixtures

Fixtures vendored from external projects. Each is preserved verbatim
unless otherwise noted. License texts are reproduced under `LICENSES/`.

## dispersa-atlassian-base

- **Source:** https://github.com/dispersa-core/dispersa
- **Path upstream:** `examples/atlassian-semantic/tokens/base.json`
- **License:** MIT (Copyright (c) 2025 Tim Gesemann) — see `LICENSES/dispersa-MIT`
- **Adaptation:** none — vendored verbatim.

## dispersa-multibrand-palette

- **Source:** https://github.com/dispersa-core/dispersa
- **Path upstream:** `examples/multi-brand/tokens/base/colors/palette.json`
- **License:** MIT (Copyright (c) 2025 Tim Gesemann) — see `LICENSES/dispersa-MIT`
- **Adaptation:** none — vendored verbatim.

## terrazzo-sizes

- **Source:** https://github.com/terrazzoapp/terrazzo
- **Path upstream:** `packages/parser/test/fixtures/refs/base/size.json`
- **License:** MIT (Copyright (c) 2024 Drew Powers) — see `LICENSES/terrazzo-MIT`
- **Adaptation:** none — vendored verbatim.

## terrazzo-colors

- **Source:** https://github.com/terrazzoapp/terrazzo
- **Path upstream:** `packages/parser/test/fixtures/refs/base/colors-light.json`
- **License:** MIT (Copyright (c) 2024 Drew Powers) — see `LICENSES/terrazzo-MIT`
- **Adaptation:** added `"$type": "color"` to three reference tokens
  (`base.color.inset`, `base.color.neutral.0`, `base.color.neutral.13`)
  to satisfy our strict mode (which requires `$type` own-or-inherited
  on every token, including reference tokens).
