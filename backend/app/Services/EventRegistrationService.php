<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventRegistrationService
{
    public function register(User $user, int $eventId): EventRegistration
    {
        return DB::transaction(function () use ($user, $eventId) {
            /** @var Event $event */
            $event = Event::query()
                ->whereKey($eventId)
                ->lockForUpdate()
                ->first();

            if ($event === null) {
                throw (new ModelNotFoundException)->setModel(Event::class, [$eventId]);
            }

            if ($event->status !== EventStatus::Published) {
                throw ValidationException::withMessages([
                    'event' => ['This event is not open for registration.'],
                ]);
            }

            if ($event->starts_at->lte(now())) {
                throw ValidationException::withMessages([
                    'event' => ['Registration is closed for past events.'],
                ]);
            }

            $confirmedCount = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('status', RegistrationStatus::Confirmed)
                ->count();

            if ($confirmedCount >= $event->capacity) {
                throw ValidationException::withMessages([
                    'event' => ['No spots available for this event.'],
                ]);
            }

            $registration = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($registration?->status === RegistrationStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'event' => ['You are already registered for this event.'],
                ]);
            }

            if ($registration?->status === RegistrationStatus::Cancelled) {
                $registration->update(['status' => RegistrationStatus::Confirmed]);

                return $registration->fresh();
            }

            try {
                return EventRegistration::query()->create([
                    'event_id' => $event->id,
                    'user_id' => $user->id,
                    'status' => RegistrationStatus::Confirmed,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    throw ValidationException::withMessages([
                        'event' => ['You are already registered for this event.'],
                    ]);
                }

                throw $e;
            }
        });
    }

    public function cancel(User $user, int $eventId): void
    {
        DB::transaction(function () use ($user, $eventId) {
            $registration = EventRegistration::query()
                ->where('event_id', $eventId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($registration === null) {
                throw (new ModelNotFoundException)->setModel(EventRegistration::class);
            }

            if ($registration->status === RegistrationStatus::Cancelled) {
                return;
            }

            $registration->update(['status' => RegistrationStatus::Cancelled]);
        });
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';

        return $sqlState === '23505'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
