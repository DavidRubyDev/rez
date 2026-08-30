---
name: sync-context
description: Compares docs/REZ-CONTEXT.md across the rez, rez-starter, rez-admin, and rez-components sibling repos and syncs them so all four carry identical, up-to-date content. Use when asked to sync/check REZ-CONTEXT.md across repos, or after making a cross-repo architectural change that this doc should reflect.
---

# sync-context

`docs/REZ-CONTEXT.md` is a single shared document describing the whole Rez ecosystem
architecture (Domain/Application/Infrastructure layers, modules, invariants, delivery plan).
It is meant to be byte-for-byte identical across all four repos: `rez`, `rez-starter`,
`rez-admin`, `rez-components`. `docs/CONTEXT.md` is a separate, intentionally per-repo
implementation log and must NOT be touched by this skill.

## Steps

1. **Locate the sibling repos.** They live as sibling directories of the repo this skill is
   running in — e.g. if this repo is at `/path/to/rez-admin`, the other three are expected at
   `/path/to/rez`, `/path/to/rez-starter`, and `/path/to/rez-components`. Resolve all four paths
   and confirm each is a git repository (`git -C <path> rev-parse --is-inside-work-tree`). Skip
   and report any repo that isn't found on disk rather than failing outright — this skill may
   run in an environment where not all four are cloned.

2. **Diff `docs/REZ-CONTEXT.md` across every repo found.** Compare every pair with `diff`. If a
   repo is missing the file entirely, treat that as a difference (it needs the file created).

3. **If every copy is identical:** report "in sync" and stop. Make no commits.

4. **If they differ, pick the canonical version:**
   - Parse the `**Last updated:**` line near the top of each copy. The most recent date wins.
   - If dates tie, or are missing/unparseable in a way that leaves it ambiguous, fall back to
     `git log -1 --format=%cI -- docs/REZ-CONTEXT.md` in each repo — the most recently committed
     copy wins.
   - Show the user a short summary before applying anything: which repos differ from the
     canonical one, and roughly what changed (a `diff` excerpt or section-level description is
     enough — no need to paste the whole file).

5. **Propagate the canonical copy** to every repo whose file doesn't match it byte-for-byte,
   overwriting `docs/REZ-CONTEXT.md` in place (creating it if it was missing).

6. **Commit the change in each modified repo**, following that repo's own `CLAUDE.md` branch
   workflow (checkout a branch with that repo's documentation-change prefix — check its
   `CLAUDE.md`, prefixes differ slightly between repos — commit with a message focused on *why*,
   no Claude attribution). Do not push in any repo — pushing is always the user's job, per every
   repo's `CLAUDE.md`.

7. **Report a summary**: which repo's copy was canonical, which repos were updated, and the
   branch/commit created in each.

## Notes

- Never edit `docs/CONTEXT.md` in any repo — it is intentionally per-repo and out of scope for
  this sync.
- This skill only reconciles `docs/REZ-CONTEXT.md`. A request to sync some other shared doc is a
  separate, explicit task.
- If the copies disagree in a way that looks like a real content conflict — e.g. two repos each
  describe *different* completed work that the other doesn't mention, rather than one simply
  being behind — stop and ask the user which side is authoritative instead of guessing. Silently
  picking "most recent" is only safe when the divergence looks like staleness, not a merge
  conflict.
