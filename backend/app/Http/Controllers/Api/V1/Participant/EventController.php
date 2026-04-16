<?php

namespace App\Http\Controllers\Api\V1\Participant;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Participant\ListParticipantEventsRequest;
use App\Http\Requests\Api\V1\Participant\ShowParticipantEventRequest;
use App\Http\Resources\ParticipantEventResource;
use App\Models\Event;
use App\Repositories\EventRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        private readonly EventRepository $events,
    ) {}

    public function index(ListParticipantEventsRequest $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $validated = $request->validated();

        $filters = array_intersect_key(
            $validated,
            array_flip(['q', 'organizer_id', 'starts_from', 'starts_until']),
        );

        $events = $this->events->paginatePublishedFutureCatalog($perPage, $filters);

        return ParticipantEventResource::collection($events);
    }

    public function show(ShowParticipantEventRequest $request, int $event): ParticipantEventResource
    {
        $eventModel = Event::query()
            ->whereKey($event)
            ->where('status', EventStatus::Published)
            ->withCount('confirmedRegistrations')
            ->firstOrFail();

        return new ParticipantEventResource($eventModel);
    }
}
