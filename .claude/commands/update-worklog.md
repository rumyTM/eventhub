---
description: Append a structured entry to WORKLOG.md recording what changed this session, decisions made, and what's next.
argument-hint: [short summary of what you did] — optional; will be inferred from the diff if omitted
allowed-tools: Read, Edit, Bash
---

Update the root `WORKLOG.md` with a new dated entry for this working session.

1. Determine what changed: `git log --oneline -n 10` and `git diff --stat` (staged + unstaged). If `$ARGUMENTS` is
   given, use it as the headline.
2. Append a new entry **at the top of the log section** (most-recent-first) using the existing format in
   `WORKLOG.md`. Include:
   - **Date** (UTC) and the phase/day from `PLAN.md` this maps to.
   - **What changed** — services/files touched, endpoints/jobs/migrations added.
   - **Decisions** — any non-obvious choice + one-line rationale (so it can feed `docs/technical-decision-log.md`).
   - **Verification** — tests run + result, formatter status.
   - **Next** — the immediate next task.
3. If a decision is significant (architecture/library/trade-off), also note that it should be promoted into
   `docs/technical-decision-log.md`.
4. Keep it concise and factual. Do not invent results — only log what actually happened.
