---
name: laravel-api-endpoint
description: Build a versioned Laravel API feature end-to-end (route, FormRequest, thin controller, service/action, JsonResource, named rate limiter, tests) following the repo's layered conventions. Use when adding or modifying an API endpoint, CRUD resource, or auth/business flow in a Laravel API project, or when the user mentions controllers, FormRequests, resources, services, or endpoints.
---

# Laravel API endpoint workflow

This skill encodes the standard, repeatable way to build an API feature in this codebase. The authoritative rules
live in `CLAUDE.md`; this is the step-by-step procedure. Always read the relevant `CLAUDE.md` first and prefer the
patterns already present in the repo over anything here.

## 0. Orient (do this every time)

Read, in order:
1. `routes/api.php` — versioning, middleware groups, naming.
2. One sibling `*Controller`, `*Request`, `*Resource`, `*Service`, and the matching `Repositories/Contracts` +
   `Repositories/Eloquent` pair near your target — copy their exact style.
3. The response helper (`ApiResponse` in `app/Support/` or `ApiResponseHelper` in `app/Helpers/`) — note the
   envelope and method names actually used.
4. The provider that binds repositories (`RepositoryServiceProvider` / `AppServiceProvider`) and existing named
   rate limiters.
5. `tests/` + `composer.json` — Pest or PHPUnit.

Determine the JSON envelope from the code, not assumption. Canonical target shape:
`{ "success": bool, "message": string, "data": object|null, "errors": object|null }` with real HTTP status codes.

## 1. Create files with artisan

Use `--no-interaction`:
```
php artisan make:request {Domain}/{Action}Request
php artisan make:controller Api/V1/{Resource}Controller
php artisan make:resource {Model}Resource
php artisan make:enum {Name}        # if a new fixed value-set is needed
php artisan make:interface Repositories/Contracts/{Model}RepositoryInterface
php artisan make:class Repositories/Eloquent/{Model}Repository
php artisan make:test {Path}Test    # add --pest or rely on the repo default; or --unit
```

Bind the interface to the implementation in `RepositoryServiceProvider::register()`
(`$this->app->bind({Model}RepositoryInterface::class, {Model}Repository::class)`).

## 2. The layers

**Route** — under `v1`, in the correct auth group, with a named throttle:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('me/leads')->controller(LeadController::class)->group(function () {
        Route::post('/', 'store')->middleware('throttle:leads-apply');
    });
});
```

**Rate limiter** (only if new) — in `configureRateLimiters()`:
```php
RateLimiter::for('leads-apply', fn (Request $r) => Limit::perHour(5)
    ->by('leads-apply:'.$r->user()?->id)
    ->response(fn ($req, $h) => ApiResponse::error(
        message: __('api.throttle.leads_apply'), status: 429,
        data: ['retry_after' => (int) $h['Retry-After']],
    )));
```

**FormRequest** — array rules, messages, normalisation helpers (not in `rules()`):
```php
public function rules(): array
{
    return ['product_id' => ['required', 'integer', 'exists:products,id']];
}
```

**Controller** — thin:
```php
public function store(StoreLeadRequest $request): JsonResponse
{
    LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

    $result = $this->leads->apply(user: $request->user(), data: $request->validated());

    return ApiResponse::success(
        data: ['lead' => new LeadResource($result['lead'])],
        message: __('api.leads.applied'),
        status: $result['created'] ? 201 : 200,
    );
}
```

**Repository** — interface + Eloquent impl; ALL query/persistence, returns models/collections/paginators:
```php
interface LeadRepositoryInterface
{
    public function latestForUserAndProduct(int $userId, int $productId): ?Lead;
    public function create(array $attributes): Lead;
}

final class LeadRepository implements LeadRepositoryInterface
{
    public function latestForUserAndProduct(int $userId, int $productId): ?Lead
    {
        return Lead::forUser($userId)->forProduct($productId)->latest()->first();
    }
    public function create(array $attributes): Lead { return Lead::create($attributes); }
}
```

**Service** — depends on the repo interface, owns the transaction/orchestration; **Action** if it's a reusable pure
computation (single `handle()`):
```php
public function __construct(private readonly LeadRepositoryInterface $leads) {}

public function apply(User $user, array $data): array
{
    $existing = $this->leads->latestForUserAndProduct($user->id, $data['product_id']);
    if ($existing && ! $existing->status->allowsReapplication()) {
        return ['lead' => $existing->load('product'), 'created' => false];
    }
    return DB::transaction(fn () => [
        'lead'    => $this->leads->create([...])->load('product'),
        'created' => true,
    ]);
}
```

**Resource** — enums as `{value,label}`, `whenLoaded`, ISO-8601:
```php
public function toArray(Request $request): array
{
    return [
        'id'     => $this->id,
        'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
        'product'=> ProductResource::make($this->whenLoaded('product')),
        'created_at' => $this->created_at?->toIso8601String(),
    ];
}
```

## 3. Tests (required, not optional)

Match the repo runner. Cover **happy path + 422 + 401 + 429** (where applicable) plus domain edge cases. Use
factories/states, `Http::fake()` for externals, `Queue::fake()` for jobs. Assert on the envelope
(`assertJsonPath('success', true)`, `assertJsonStructure`) and use semantic status assertions.

## 4. Definition of done

- `composer format` (Pint) clean.
- Relevant tests pass (`php artisan test --filter=...`).
- Controller → Service → Repository → Model respected: no business logic in the controller, no Eloquent query
  building outside a repository, no `$request->all()`, no raw `response()->json()`, no `DB::table()`.
- No PAN/CVV/OTP/token/secret/credential anywhere — use `[PLACEHOLDER]`; sensitive logging redacted.

## Anti-patterns to refuse

- Validating inside the controller instead of a FormRequest.
- Returning a raw model/array instead of a Resource.
- HTTP 200 with an error code in the body.
- Hard-coded throttle limits in routes.
- MySQL `ENUM` columns or string constants instead of backed enums.
- Querying Eloquent directly from a service/controller instead of through the repository.
- A repository that holds business logic / transactions, or a generic `BaseRepository` / empty pass-through repo.
- Converting an existing Pest suite to PHPUnit (or vice-versa).
