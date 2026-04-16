<?php

namespace App\Http\Controllers\Api\V1\Organizer;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organizer\StoreEventRequest;
use App\Http\Requests\Api\V1\Organizer\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrganizerEventRegistrationResource;
use App\Jobs\NotifyRegisteredParticipantsEventCancelled;
use App\Models\Event;
use App\Repositories\EventRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly EventRepository $events,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Event::class);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $paginator = $this->events->paginateOrganizerEvents($request->user(), $perPage);

        return EventResource::collection($paginator);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();

        $event = Event::query()->create([
            ...$data,
            'organizer_id' => $request->user()->id,
            'status' => EventStatus::Published,
        ]);

        return (new EventResource($event))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Event $event): EventResource
    {
        $this->authorize('view', $event);

        return new EventResource($event);
    }

    public function registrations(Request $request, Event $event): AnonymousResourceCollection
    {
        $this->authorize('viewRegistrations', $event);

        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $registrations = $event->registrations()
            ->with(['user' => fn ($q) => $q->select(['id', 'name', 'email'])])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return OrganizerEventRegistrationResource::collection($registrations);
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $event->update($request->validated());

        return new EventResource($event->fresh());
    }

    public function cancel(Event $event): EventResource
    {
        $this->authorize('cancel', $event);

        $wasPublished = $event->status === EventStatus::Published;

        $event->update([
            'status' => EventStatus::Cancelled,
        ]);

        if ($wasPublished) {
            NotifyRegisteredParticipantsEventCancelled::dispatch($event->id);
        }

        return new EventResource($event->fresh());
    }
}
