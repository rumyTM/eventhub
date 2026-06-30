<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTypes\StoreTicketTypeRequest;
use App\Http\Requests\TicketTypes\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Events\TicketTypeService;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Ticket Types
 *
 * Manage ticket types for an event. Public reads; vendor-role writes (own events only).
 */
final class TicketTypeController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly TicketTypeService $ticketTypes,
    ) {}

    /**
     * List ticket types
     *
     * Returns all active ticket types for the given event, with pricing and availability.
     * Requires the same visibility as the parent event (published events are public).
     *
     * @group Public
     *
     * @subgroup Ticket Types
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Ticket types retrieved.","data":{"ticket_types":[{"id":"01JWXYZ000000000000TICKET1","event_id":"01JWXYZ0000000000000EVENT1","kind":{"value":"general","label":"General"},"price":50000,"currency":"BDT","quantity_total":200,"quantity_sold":12,"group_size":null,"group_discount":null,"sales_start":"2026-08-01T00:00:00+06:00","sales_end":"2026-09-19T23:59:59+06:00","created_at":"2026-06-30T09:00:00+00:00","updated_at":"2026-06-30T09:00:00+00:00"}],"pagination":{"current_page":1,"per_page":25,"total":1,"last_page":1}},"errors":null}
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        // Reuse the event's visibility rule for its ticket types.
        Gate::forUser($this->actingUser($request))->authorize('view', $event);

        $page = $this->ticketTypes->list($event, self::PER_PAGE);

        return ApiResponse::success(
            data: [
                'ticket_types' => TicketTypeResource::collection($page->getCollection()),
                'pagination' => $this->pagination($page),
            ],
            message: __('api.ticket_types.listed'),
        );
    }

    /**
     * Get ticket type
     *
     * Retrieve a single ticket type including group-bundle rules if applicable.
     *
     * @group Public
     *
     * @subgroup Ticket Types
     *
     * @unauthenticated
     *
     * @response 200 scenario="Success" {"success":true,"message":"Ticket type retrieved.","data":{"ticket_type":{"id":"01JWXYZ000000000000TICKET1","event_id":"01JWXYZ0000000000000EVENT1","kind":{"value":"general","label":"General"},"price":50000,"currency":"BDT","quantity_total":200,"quantity_sold":12,"group_size":null,"group_discount":null,"sales_start":"2026-08-01T00:00:00+06:00","sales_end":"2026-09-19T23:59:59+06:00","created_at":"2026-06-30T09:00:00+00:00","updated_at":"2026-06-30T09:00:00+00:00"}},"errors":null}
     */
    public function show(Request $request, Event $event, TicketType $ticketType): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        Gate::forUser($this->actingUser($request))->authorize('view', $ticketType);

        return ApiResponse::success(
            data: ['ticket_type' => new TicketTypeResource($ticketType)],
            message: __('api.ticket_types.retrieved'),
        );
    }

    /**
     * Create ticket type
     *
     * Add a ticket type (general, VIP, early-bird, or group-bundle) to the vendor's own event.
     *
     * @group Vendor
     *
     * @subgroup Ticket Types
     *
     * @authenticated
     */
    public function store(StoreTicketTypeRequest $request, Event $event): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('create', [TicketType::class, $event]);

        $ticketType = $this->ticketTypes->create($event, $request->validated());

        return ApiResponse::success(
            data: ['ticket_type' => new TicketTypeResource($ticketType)],
            message: __('api.ticket_types.created'),
            status: 201,
        );
    }

    /**
     * Update ticket type
     *
     * @group Vendor
     *
     * @subgroup Ticket Types
     *
     * @authenticated
     */
    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticketType): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('update', $ticketType);

        $ticketType = $this->ticketTypes->update($ticketType, $request->validated());

        return ApiResponse::success(
            data: ['ticket_type' => new TicketTypeResource($ticketType)],
            message: __('api.ticket_types.updated'),
        );
    }

    /**
     * Delete ticket type
     *
     * Soft-delete a ticket type. Only allowed if no paid orders reference it.
     *
     * @group Vendor
     *
     * @subgroup Ticket Types
     *
     * @authenticated
     */
    public function destroy(Request $request, Event $event, TicketType $ticketType): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        $this->authorize('delete', $ticketType);

        $this->ticketTypes->delete($ticketType);

        return ApiResponse::success(message: __('api.ticket_types.deleted'));
    }

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
