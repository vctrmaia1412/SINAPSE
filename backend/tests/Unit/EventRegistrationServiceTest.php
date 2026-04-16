<?php

namespace Tests\Unit;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\EventRegistrationService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventRegistrationServiceTest extends TestCase
{
    private EventRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EventRegistrationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_register_throws_when_event_already_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00 UTC'));
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::parse('2026-06-10 10:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-06-10 14:00:00 UTC'),
            'capacity' => 10,
            'status' => EventStatus::Published,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->register($participant, $event->id);
    }

    public function test_register_throws_when_already_confirmed(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->for($organizer, 'organizer')->inTheFuture()->create([
            'capacity' => 10,
            'status' => EventStatus::Published,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->register($participant, $event->id);
    }

    public function test_register_succeeds_when_spots_available(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->for($organizer, 'organizer')->inTheFuture()->create([
            'capacity' => 5,
            'status' => EventStatus::Published,
        ]);

        $registration = $this->service->register($participant, $event->id);

        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame($event->id, $registration->event_id);
        $this->assertSame($participant->id, $registration->user_id);
    }
}
