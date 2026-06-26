---
description: Scaffold a full resourceful CRUD API for a model (routes, FormRequests, controller, service, repository, resource, tests) following CLAUDE.md.
argument-hint: <Model> [under <prefix>] — e.g. "Product" or "Lead under me"
allowed-tools: Read, Grep, Glob, Edit, Write, Bash
---
> **Monorepo note.** This command operates inside a single Laravel service — either `services/core-api` or `services/payment-service`. `cd` into the right service first; read that service's `CLAUDE.md` (and the root `CLAUDE.md` for cross-service context) before generating.


Scaffold complete resourceful CRUD for the **$ARGUMENTS** model, respecting **Controller → Service → Repository → Model**
and the rest of `CLAUDE.md`. Do not over-engineer: generate only the actions that make sense for the model, skip any
the user excludes, and keep the repository lean (only the methods these actions need).

Default actions: `index` (paginated list), `show`, `store`, `update`, `destroy`.

Before generating, read `routes/api.php`, a sibling controller/service/repository/resource pair, and the response
helper so names and the envelope match the repo. Use `php artisan make:*` (`--no-interaction`).

Build, in this order:

1. **Migration/model** — only if the model does not yet exist (`make:model -mfs` for migration+factory+seeder).
   Confirm fields with the user if creating new. Add `casts()`, scopes, relationships, `SoftDeletes` if appropriate.
2. **Repository** — `{Model}RepositoryInterface` (in `Repositories/Contracts/`) + `{Model}Repository`
   (in `Repositories/Eloquent/`). Methods for exactly what the actions need: `paginate(...)`, `find(int $id)`,
   `create(array)`, `update({Model}, array)`, `delete({Model})`. Returns models/collections/paginators only — no
   business logic, no transactions. Bind interface→impl in the repository provider.
3. **FormRequests** — `Store{Model}Request` and `Update{Model}Request` (array rules; `update` rules typically use
   `sometimes`). Derive `in:` rules from enums via `Rule::in(array_column(Enum::cases(), 'value'))`.
4. **Service** — `{Model}Service` injecting the repository **interface**; one method per action; `DB::transaction()`
   for writes; throws domain exceptions (e.g. not-found → let the global handler render 404, or throw a domain 404).
5. **Controller** — `Api/V1/{Model}Controller` with thin `index/show/store/update/destroy`; `LogHelper::landingLog`
   first line; inject the **service**; `$request->validated()`; return `{Model}Resource` (collection for `index`,
   with pagination meta in `data`) wrapped in `ApiResponse`, correct status codes (`201` for store, `200`/`204` for
   destroy per repo convention).
6. **Resource** — enums as `{value,label}`, `whenLoaded` relations, ISO-8601 timestamps.
7. **Routes** — `Route::prefix('v1')` (+ any sub-prefix), grouped under the right auth middleware, with named
   throttle limiters for reads vs writes. Add limiters to the provider if missing.
8. **Tests** — for each action: happy path + 422 (store/update) + 401 (auth guard) + 429 (where limited) + 404
   (show/update/destroy on a missing id) + forbidden (if ownership applies). Factories/states; `Http::fake()` /
   `Queue::fake()` for externals. Match the repo's Pest/PHPUnit style.
9. **Finish** — `composer format`, then `php artisan test --filter={Model}`. Report every file created and the test
   result. No PAN/OTP/token/secret in code — use `[PLACEHOLDER]`.
