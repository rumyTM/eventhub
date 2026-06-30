<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Events
 *
 * Browse the event catalog and manage vendor events.
 * Public read endpoints require no authentication. Write endpoints require a `vendor` role token.
 */
final class EventController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly EventService $events,
    ) {}

    /**
     * List events
     *
     * Returns published events for unauthenticated callers. Vendors additionally see their own
     * draft/ongoing events; admins see all events regardless of status.
     *
     * @group Public
     *
     * @subgroup Events
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Events retrieved.","data":{"events":[{"id":"01JWXYZ0000000000000EVENT1","vendor_id":"01JWXYZ0000000000000VENDOR","title":"Summer Music Festival 2026","description":"An evening of live music at the Dhaka Convention Centre.","timezone":"Asia/Dhaka","starts_at":"2026-09-20T12:00:00+00:00","ends_at":"2026-09-20T16:00:00+00:00","capacity":500,"status":{"value":"published","label":"Published"},"ticket_types":[],"created_at":"2026-06-30T09:00:00+00:00","updated_at":"2026-06-30T09:00:00+00:00"}],"pagination":{"current_page":1,"per_page":15,"total":1,"last_page":1}},"errors":null}
     */
    public function index(Request $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $page = $this->events->list($this->actingUser($request), self::PER_PAGE);

        return ApiResponse::success(
            data: [
                'events' => EventResource::collection($page->getCollection()),
                'pagination' => $this->pagination($page),
            ],
            message: __('api.events.listed'),
        );
    }

    /**
     * Get event
     *
     * Retrieve a single event with its ticket types. Published events are public;
     * draft/cancelled events are visible only to their owner vendor or an admin.
     *
     * @group Public
     *
     * @subgroup Events
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Event retrieved.","data":{"event":{"id":"01JWXYZ0000000000000EVENT1","vendor_id":"01JWXYZ0000000000000VENDOR","title":"Summer Music Festival 2026","description":"An evening of live music at the Dhaka Convention Centre.","timezone":"Asia/Dhaka","starts_at":"2026-09-20T12:00:00+00:00","ends_at":"2026-09-20T16:00:00+00:00","capacity":500,"status":{"value":"published","label":"Published"},"ticket_types":[{"id":"01JWXYZ000000000000TICKET1","event_id":"01JWXYZ0000000000000EVENT1","kind":{"value":"general","label":"General"},"price":50000,"currency":"BDT","quantity_total":200,"quantity_sold":12,"group_size":null,"group_discount":null,"sales_start":"2026-08-01T00:00:00+06:00","sales_end":"2026-09-19T23:59:59+06:00","created_at":"2026-06-30T09:00:00+00:00","updated_at":"2026-06-30T09:00:00+00:00"}],"created_at":"2026-06-30T09:00:00+00:00","updated_at":"2026-06-30T09:00:00+00:00"}},"errors":null}
     * @response 403 scenario="Draft event (not owner)" {"success":false,"message":"This action is unauthorized.","data":null,"errors":null}
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        Gate::forUser($this->actingUser($request))->authorize('view', $event);

        $event->load('ticketTypes');

        return ApiResponse::success(
            data: ['event' => new EventResource($event)],
            message: __('api.events.retrieved'),
        );
    }

    /**
     * Create event
     *
     * Create a new event draft owned by the authenticated vendor.
     * The vendor must be KYC-verified before the event can be published.
     *
     * @group Vendor
     *
     * @subgroup Events
     *
     * @authenticated
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('create', Event::class);

        $event = $this->events->create($request->user(), $request->validated());

        return ApiResponse::success(
            data: ['event' => new EventResource($event)],
            message: __('api.events.created'),
            status: 201,
        );
    }

    /**
     * Update event
     *
     * Update a vendor's own event. Status transitions (e.g. draft → published) are enforced by
     * the event lifecycle policy (e.g. vendor must be KYC-verified to publish).
     *
     * @group Vendor
     *
     * @subgroup Events
     *
     * @authenticated
     */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('update', $event);

        $event = $this->events->update($event, $request->validated());

        return ApiResponse::success(
            data: ['event' => new EventResource($event)],
            message: __('api.events.updated'),
        );
    }

    /**
     * Delete event
     *
     * Soft-delete a vendor's own draft event. Published or ongoing events cannot be deleted
     * (cancel them instead via the status transition).
     *
     * @group Vendor
     *
     * @subgroup Events
     *
     * @authenticated
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('delete', $event);

        $this->events->delete($event);

        return ApiResponse::success(message: __('api.events.deleted'));
    }

    /** Resolve the optional bearer-token user on public read routes (no auth middleware there). */
    private function actingUser(Request $request): ?User
    {
        return $request->user() ?? auth('sanctum')->user();
    }

    /**
     * @return array<string, int>
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ];
    }
}
