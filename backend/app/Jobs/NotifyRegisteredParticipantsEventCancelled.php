<?php

namespace App\Jobs;

use App\Models\Event;
use App\Notifications\EventCancelledNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyRegisteredParticipantsEventCancelled implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $eventId,
    ) {}

    public function handle(): void
    {
        $event = Event::query()->find($this->eventId);
        if ($event === null) {
            return;
        }

        foreach ($event->confirmedRegistrations()->with('user')->cursor() as $registration) {
            $registration->user->notify(new EventCancelledNotification($event));
        }
    }
}
