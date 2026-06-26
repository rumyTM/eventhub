---
description: Produce a focused execution plan for one day/phase of the EventHub build, grounded in PLAN.md and the assessment rubric.
argument-hint: <day number 1-5> — e.g. "3"
allowed-tools: Read, Grep, Glob, Bash
---

Generate a concrete, ordered task list for **Day $ARGUMENTS** of the EventHub assessment.

1. Read `PLAN.md` (phase definitions + priority matrix) and `WORKLOG.md` (what's already done) so the plan reflects
   real current state, not a generic restart.
2. Read the relevant per-service `CLAUDE.md` for the day's focus.
3. Output an ordered checklist of tasks for the day, each with: the service it lives in, the files/artifacts it
   produces, the test(s) it needs, and its rubric tie-in (which scoring category it earns points in).
4. Call out dependencies (what must exist first) and the must-have vs nice-to-have line for the day — if time runs
   short, what gets cut.
5. End with the day's "definition of done" and a reminder to run `/format-and-test` and `/update-worklog`.

Do not start coding — this command only plans. Prioritise ruthlessly: a smaller, tested, documented slice beats a
broad untested one.
