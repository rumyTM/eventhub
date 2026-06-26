---
description: Create or fix an Eloquent API Resource for a model following CLAUDE.md (enums as {value,label}, whenLoaded relations, ISO-8601 dates).
argument-hint: <Model name> — e.g. "Lead" or "Product"
allowed-tools: Read, Grep, Glob, Edit, Write, Bash
---
> **Monorepo note.** This command operates inside a single Laravel service — either `services/core-api` or `services/payment-service`. `cd` into the right service first; read that service's `CLAUDE.md` (and the root `CLAUDE.md` for cross-service context) before generating.


Create (or correct) the `JsonResource` for the **$ARGUMENTS** model.

1. Read the model: its `$fillable`, `casts()` (note enum-cast and decimal columns), and relationship methods.
2. Read one existing `*Resource` in `app/Http/Resources/` to match this repo's enum-output helper and field style.
3. Generate `php artisan make:resource {Model}Resource --no-interaction` if it does not exist.
4. In `toArray()`:
   - Output every enum-cast attribute as `{ "value": ..., "label": ... }` — never a bare scalar.
   - Expose relationships **only** via `$this->whenLoaded('relation')`, nesting the related Resource.
   - Format datetimes with `toIso8601String()`, dates with `toDateString()`.
   - Cast money columns explicitly (`(float)`/string) consistent with the repo.
   - Gate user-specific/computed fields behind `$this->when(...)`; pass computed inputs via the constructor, not
     `->additional()`.
5. Do not eager-load inside the resource. If a controller/service needs the relation, load it there.
6. Run `composer format`. If a test references this resource's shape, run it.
