---
phase: 01-foundation
plan: 01
subsystem: infra
tags: [laravel, laravel-ai, sqlite, git, orphan-reset]

# Dependency graph
requires: []
provides:
  - Fresh Laravel 13 scaffold on orphan `main` branch (bb98f63)
  - `laravel/ai` v0.6.3 installed and published with `config/ai.php`
  - SQLite configured as default database (`DB_CONNECTION=sqlite`)
  - All default + AI SDK migrations applied (`agent_conversations`, `agent_conversation_messages`)
  - `legacy/v1` branch preserving full prior codebase including planning artifacts (ef5d869)
  - `.planning/`, `CLAUDE.md`, `SKD.md` preserved on both `main` and `legacy/v1`
affects: [01-02, 01-03]

# Tech tracking
tech-stack:
  added:
    - laravel/framework v13.6.0 (was v12.x on legacy)
    - laravel/ai v0.6.3 (new — replaces prism-php/prism)
    - SQLite (was MariaDB on legacy)
  patterns:
    - Orphan branch for clean-slate resets — no shared history between main and legacy/v1
    - .planning/ committed alongside code — planning artifacts survive orphan reset

key-files:
  created:
    - config/ai.php (Laravel AI SDK provider configuration)
    - database/migrations/2026_04_27_170106_create_agent_conversations_table.php (AI SDK tables)
    - database/database.sqlite (empty SQLite corpus)
  modified:
    - composer.json (laravel/framework ^13.0, laravel/ai ^0.6)
    - .env (DB_CONNECTION=sqlite)
    - .gitignore (added /.claude/ to prevent worktree from being tracked)

key-decisions:
  - "legacy/v1 points to FEAT-add-providers-for-each-LLMS HEAD (ef5d869) not bare master — preserves planning artifacts alongside old codebase"
  - "main is an orphan branch (bb98f63) — no shared history with legacy/v1"
  - "laravel/ai v0.6.3 selected over prism-php/prism per stack correction decision in user memory"
  - "SQLite as default DB — zero-config, single-file corpus, Laravel 13 default"

patterns-established:
  - "Git orphan reset pattern: preserve to temp, orphan checkout, clean, scaffold, restore, commit, rename, force-push"
  - ".claude/ excluded from git via .gitignore to prevent agent worktree tracking"

requirements-completed:
  - SETUP-01

# Metrics
duration: ~35min
completed: 2026-04-27
---

# Phase 01 Plan 01: Repository Reset Summary

**Orphan main branch created with Laravel 13.6.0 + laravel/ai v0.6.3, prior codebase preserved on legacy/v1 — zero shared history between branches**

## Status

**PARTIAL — Awaiting human action for remote pushes (auth gate)**

Tasks 1 and 2 are complete locally. Task 3 (human-verify checkpoint) requires:
1. User to configure GitHub SSH key or HTTPS token, then run remote pushes
2. User to verify the 10-step checklist and type "approved"

## Performance

- **Duration:** ~35 min
- **Started:** 2026-04-27T14:00:00Z (approximate)
- **Completed (partial):** 2026-04-27T14:35:00Z
- **Tasks completed:** 2 of 3 (Task 3 is checkpoint — awaiting human action)
- **Files modified:** ~65 (full Laravel 13 scaffold + laravel/ai)

## Accomplishments

- `legacy/v1` created from `FEAT-add-providers-for-each-LLMS` HEAD (ef5d869) — preserves all old PHP code AND planning artifacts
- `main` is now an orphan branch with exactly 1 commit: the Laravel 13 scaffold
- `laravel/ai` v0.6.3 installed, `config/ai.php` published, all migrations applied
- SQLite configured as default database, `database.sqlite` created
- `.planning/`, `CLAUDE.md`, `SKD.md` all preserved on `main`
- No shared history between `main` and `legacy/v1` (merge-base check passes)

## Task Commits

1. **Task 1: Preserve legacy code on `legacy/v1`** — Local branch created (ef5d869 = legacy/v1 tip). Remote push PENDING (auth gate).
2. **Task 2: Orphan-reset `main` and install Laravel 13 scaffold** — `bb98f63` (`chore: fresh Laravel 13 scaffold with laravel/ai SDK`). Remote force-push PENDING (auth gate).
3. **Task 3: Human verification** — CHECKPOINT: awaiting user approval.

## Files Created/Modified

- `composer.json` — laravel/framework ^13.0, laravel/ai ^0.6
- `composer.lock` — resolved dependencies including laravel/ai v0.6.3
- `config/ai.php` — Laravel AI SDK provider configuration (published from vendor)
- `database/database.sqlite` — empty SQLite corpus
- `database/migrations/2026_04_27_170106_create_agent_conversations_table.php` — AI SDK conversation + message tables
- `.env` — DB_CONNECTION=sqlite
- `.env.example` — DB_CONNECTION=sqlite (commented-out MySQL lines)
- `.gitignore` — added /.claude/ to exclude agent worktree
- `.planning/` — all planning artifacts preserved and committed on main
- `CLAUDE.md` — preserved and committed on main
- `SKD.md` — preserved and committed on main
- `stubs/` — agent, structured-agent, tool, agent-middleware stubs from laravel/ai

## Decisions Made

1. **legacy/v1 points to FEAT branch, not bare master**: The planning artifacts were only on `FEAT-add-providers-for-each-LLMS`, not `master`. Pointing `legacy/v1` at the FEAT branch HEAD preserves everything. The plan's example commit subjects (`docs(state): record phase 1 context session`) matched FEAT HEAD, confirming this interpretation.

2. **.claude/ added to .gitignore**: The agent worktree created by gsd-tools at `.claude/worktrees/agent-a41923bfe175f3d2b` contains a nested git repo. Added `/.claude/` to `.gitignore` to prevent it from being staged as an embedded repo.

3. **laravel/ai used instead of prism-php/prism**: User's auto-memory records a stack correction to use `laravel/ai` not `prism-php`. The plan itself references `laravel/ai ^0.6`. This is consistent.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added /.claude/ to .gitignore**
- **Found during:** Task 2 (staging the scaffold commit)
- **Issue:** The `.claude/worktrees/agent-a41923bfe175f3d2b` worktree is a nested git repo. `git add -A` triggered "adding embedded git repository" warning. Without the .gitignore entry, the agent worktree would be tracked as a submodule reference.
- **Fix:** Added `/.claude/` to `.gitignore` before the initial commit.
- **Files modified:** `.gitignore`
- **Verification:** `git status` no longer shows `.claude/` as untracked; no submodule warning on commit.
- **Committed in:** bb98f63 (Task 2 scaffold commit)

**2. [Rule 1 - Bug] legacy/v1 pointed to FEAT branch HEAD, not bare master**
- **Found during:** Task 1 (after initially pointing to master)
- **Issue:** The plan says `git branch legacy/v1 master` but the acceptance criteria show planning commit subjects as legacy/v1 HEAD. `.planning/` only exists on the FEAT branch. Using bare `master` (5567cab) would have created legacy/v1 without planning files, and the preservation step in Task 2 would have found no `.planning/` to copy.
- **Fix:** Deleted the initially-created `legacy/v1` (pointing to 5567cab) and recreated from `FEAT-add-providers-for-each-LLMS` HEAD (ef5d869).
- **Files modified:** git refs only
- **Verification:** `git log legacy/v1 -1 --format=%s` shows "docs(state): update execution status and plan count for phase 01"; `test -f .planning/phases/01-foundation/01-01-PLAN.md` passes on the legacy/v1 tree.
- **Committed in:** N/A (git branch operation only)

---

**Total deviations:** 2 auto-fixed (1 bug, 1 missing critical)
**Impact on plan:** Both auto-fixes were necessary for correctness. No scope creep.

## Authentication Gate Encountered

**Where:** Task 1 step 6 (`git push origin legacy/v1`) and Task 2 step 12 (`git push origin main --force`)

**Cause:** SSH key at `~/.ssh/id_rsa` is not authorized on GitHub (`Permission denied (publickey)`). No HTTPS credentials or token configured.

**Status:** Both local branches are fully configured. Only remote sync is missing.

**User action required (before Task 3 checkpoint approval):**
```bash
# Option A: Add SSH key to GitHub account
# 1. Copy the public key:
cat ~/.ssh/id_rsa.pub
# 2. Add to: https://github.com/settings/keys

# Then run:
git -C /home/rec/Documents/Faculdade/pc3-submission-evaluator push origin legacy/v1
git -C /home/rec/Documents/Faculdade/pc3-submission-evaluator push origin main --force

# Option B: Use HTTPS with personal access token
git -C /home/rec/Documents/Faculdade/pc3-submission-evaluator remote set-url origin https://github.com/pedrozampier/pc3-submission-evaluator.git
git -C /home/rec/Documents/Faculdade/pc3-submission-evaluator push origin legacy/v1
git -C /home/rec/Documents/Faculdade/pc3-submission-evaluator push origin main --force
# (will prompt for username and token)
```

## Key SHAs for Traceability

| Branch | SHA | Description |
|--------|-----|-------------|
| `legacy/v1` | `ef5d869` | Full prior codebase + all planning artifacts (FEAT-add-providers-for-each-LLMS HEAD at time of plan execution) |
| Original `master` | `5567cab` | Original PHP codebase before planning work was added |
| `main` (new) | `bb98f63` | Fresh Laravel 13.6.0 + laravel/ai v0.6.3 scaffold |

## Next Phase Readiness

**Blocked by:** Human action needed — remote pushes and checkpoint approval.

After approval, Plan 02 can proceed to create:
- `ProviderResult` DTO (`app/DTOs/ProviderResult.php`)
- `Pc3Category` enum (`app/DTOs/Pc3Category.php`)
- Shared ObjectSchema for laravel/ai structured output

The scaffold is ready: Laravel 13 is installed, `laravel/ai` SDK is wired, SQLite is configured, and all default migrations are applied.

## Known Stubs

None — this plan creates no application code, only infrastructure.

---
*Phase: 01-foundation*
*Plan: 01*
*Status: Partial — checkpoint at Task 3*
*Completed: 2026-04-27*

## Self-Check: PASSED
- `bb98f63` commit exists: YES (`git log main --oneline` shows it)
- `ef5d869` is legacy/v1: YES (`git rev-parse legacy/v1` = ef5d869)
- `config/ai.php` exists: YES (`test -s config/ai.php` passes)
- `database/database.sqlite` exists: YES
- `.planning/phases/01-foundation/01-01-PLAN.md` exists on main: YES (committed at bb98f63)
- All migrations Ran: YES (users, cache, jobs, agent_conversations)
