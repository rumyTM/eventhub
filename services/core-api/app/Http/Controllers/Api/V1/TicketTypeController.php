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

final class TicketTypeController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly TicketTypeService $ticketTypes,
    ) {}

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

    public function show(Request $request, Event $event, TicketType $ticketType): JsonResponse
    {
        LogHelper::landingLog($request, __CLASS__.' - '.__FUNCTION__);

        Gate::forUser($this->actingUser($request))->authorize('view', $ticketType);

        return ApiResponse::success(
            data: ['ticket_type' => new TicketTypeResource($ticketType)],
            message: __('api.ticket_types.retrieved'),
        );
    }

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
