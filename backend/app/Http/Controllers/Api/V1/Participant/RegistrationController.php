<?php

namespace App\Http\Controllers\Api\V1\Participant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Participant\CancelEventRegistrationRequest;
use App\Http\Requests\Api\V1\Participant\ListMyEventRegistrationsRequest;
use App\Http\Requests\Api\V1\Participant\RegisterForEventRequest;
use App\Http\Resources\EventRegistrationResource;
use App\Services\EventRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly EventRegistrationService $eventRegistrationService
    ) {}

    public function store(RegisterForEventRequest $request, int $event): JsonResponse
    {
        $registration = $this->eventRegistrationService->register(
            $request->user(),
            $event,
        );

        $registration->load(['event' => fn ($q) => $q->withCount('confirmedRegistrations')]);

        return (new EventRegistrationResource($registration))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(CancelEventRegistrationRequest $request, int $event): Response
    {
        $this->eventRegistrationService->cancel($request->user(), $event);

        return response()->noContent();
    }

    public function index(ListMyEventRegistrationsRequest $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $registrations = $request->user()
            ->eventRegistrations()
            ->with(['event' => fn ($q) => $q->withCount('confirmedRegistrations')])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return EventRegistrationResource::collection($registrations);
    }
}
