# CLAUDE.md

Instructions for Claude (and other coding agents) when working in this repo.

## After completing a task

Check whether `ROADMAP.md` needs updating. It's the authoritative, on-disk
source of project scope and status — what's done, what's v1-remaining,
what's deferred (and why), what's v2, what's out of scope.

Update it whenever any of the following shifts:

- A v1-remaining item moves to done (move it from the *v1 remaining*
  section into *v1 done*).
- A new feature is deliberately deferred with a technical reason (add it
  to *Deferred within v1* with the reason + current workaround, or to
  *Explicitly out of scope* if it's a hard no).
- An architectural decision is made that future contributors shouldn't
  relitigate (add a one-liner with rationale to *Architectural decisions
  worth preserving*).
- An open question gets resolved (move it to *Resolved open questions*).
- Test counts, coverage %, or similar headline numbers change
  meaningfully (refresh *Current status*).

Do **not** turn `ROADMAP.md` into a changelog or a wishlist. It tracks
committed scope, not activity. YAGNI applies.

## Commit attribution

When creating a commit on behalf of the user, attribute the agent using an
`Assisted-by:` trailer — **not** `Co-Authored-By:`.

Format:

```
Assisted-by: AGENT_NAME:MODEL_VERSION [TOOL1] [TOOL2]
```

Example:

```
Assisted-by: Claude:claude-3-opus-4.7
```

Include bracketed tool tags after the model version when a specific tool
(editor plugin, harness, etc.) was used, e.g.
`Assisted-by: Claude:claude-opus-4-7 [claude-code]`.

**Do not** use `Co-Authored-By:` to attribute agent authorship. The author
of the commit is the human who directed the work; the agent is assistance,
not co-authorship.
