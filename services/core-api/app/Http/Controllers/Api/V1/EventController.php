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

final class EventController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly EventService $events,
    ) {}

    /** Public catalog (published only); vendors see their own, admins see all. */
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
