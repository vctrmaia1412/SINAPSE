<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventCancelledNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithApiTokens;
use Tests\TestCase;

class OrganizerEventApiTest extends TestCase
{
    use InteractsWithApiTokens;

    public function test_organizer_creates_event(): void
    {
        $organizer = User::factory()->organizer()->create();

        $starts = Carbon::now()->addWeek();
        $ends = $starts->copy()->addHours(3);

        $response = $this->postJson('/api/v1/organizer/events', [
            'title' => 'Laravel Day',
            'description' => 'Workshop',
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
            'capacity' => 50,
        ], [
            'Authorization' => 'Bearer '.$this->bearer($organizer),
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Laravel Day')
            ->assertJsonPath('data.organizer_id', $organizer->id)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.capacity', 50);
    }

    public function test_organizer_lists_only_own_events(): void
    {
        $a = User::factory()->organizer()->create();
        $b = User::factory()->organizer()->create();

        $own = Event::factory()->for($a, 'organizer')->create(['title' => 'Mine']);
        Event::factory()->for($b, 'organizer')->create(['title' => 'Other']);

        $response = $this->getJson('/api/v1/organizer/events', [
            'Authorization' => 'Bearer '.$this->bearer($a),
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertSame(1, $ids->count());
        $row = collect($response->json('data'))->firstWhere('id', $own->id);
        $this->assertIsArray($row);
        $this->assertArrayHasKey('confirmed_registrations_count', $row);
        $this->assertSame(0, $row['confirmed_registrations_count']);
    }

    public function test_organizer_views_own_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create();

        $this->getJson("/api/v1/organizer/events/{$event->id}", [
            'Authorization' => 'Bearer '.$this->bearer($organizer),
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $event->id);
    }

    public function test_organizer_updates_own_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create(['title' => 'Old']);

        $this->patchJson("/api/v1/organizer/events/{$event->id}", [
            'title' => 'New Title',
        ], [
            'Authorization' => 'Bearer '.$this->bearer($organizer),
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title');
    }

    public function test_organizer_cancels_own_event(): void
    {
        Notification::fake();

        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create([
            'status' => EventStatus::Published,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->postJson("/api/v1/organizer/events/{$event->id}/cancel", [], [
            'Authorization' => 'Bearer '.$this->bearer($organizer),
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        Notification::assertSentTo(
            $participant,
            EventCancelledNotification::class,
        );
    }

    public function test_organizer_cannot_access_another_organizers_event(): void
    {
        $owner = User::factory()->organizer()->create();
        $intruder = User::factory()->organizer()->create();
        $event = Event::factory()->for($owner, 'organizer')->create();

        $this->getJson("/api/v1/organizer/events/{$event->id}", [
            'Authorization' => 'Bearer '.$this->bearer($intruder),
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_participant_cannot_create_or_update_organizer_events(): void
    {
        $participant = User::factory()->participant()->create();
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create();

        $starts = Carbon::now()->addWeek();
        $ends = $starts->copy()->addHours(2);

        $this->postJson('/api/v1/organizer/events', [
            'title' => 'Blocked',
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
            'capacity' => 10,
        ], [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])->assertForbidden();

        $this->patchJson("/api/v1/organizer/events/{$event->id}", [
            'title' => 'Hacked',
        ], [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_organizer_cannot_update_another_organizers_event(): void
    {
        $owner = User::factory()->organizer()->create();
        $intruder = User::factory()->organizer()->create();
        $event = Event::factory()->for($owner, 'organizer')->create(['title' => 'Owned']);

        $this->patchJson("/api/v1/organizer/events/{$event->id}", [
            'title' => 'Tampered',
        ], [
            'Authorization' => 'Bearer '.$this->bearer($intruder),
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_organizer_cannot_cancel_another_organizers_event(): void
    {
        $owner = User::factory()->organizer()->create();
        $intruder = User::factory()->organizer()->create();
        $event = Event::factory()->for($owner, 'organizer')->create([
            'status' => EventStatus::Published,
        ]);

        $this->postJson("/api/v1/organizer/events/{$event->id}/cancel", [], [
            'Authorization' => 'Bearer '.$this->bearer($intruder),
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_participant_cannot_list_organizer_events(): void
    {
        $participant = User::factory()->participant()->create();

        $this->getJson('/api/v1/organizer/events', [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_organizer_lists_registrations_for_own_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create();

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->getJson("/api/v1/organizer/events/{$event->id}/registrations", [
            'Authorization' => 'Bearer '.$this->bearer($organizer),
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.user.email', $participant->email)
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    public function test_organizer_cannot_list_registrations_for_another_organizers_event(): void
    {
        $owner = User::factory()->organizer()->create();
        $intruder = User::factory()->organizer()->create();
        $event = Event::factory()->for($owner, 'organizer')->create();

        $this->getJson("/api/v1/organizer/events/{$event->id}/registrations", [
            'Authorization' => 'Bearer '.$this->bearer($intruder),
            'Accept' => 'application/json',
        ])->assertForbidden();
    }

    public function test_participant_cannot_list_organizer_event_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $event = Event::factory()->for($organizer, 'organizer')->create();

        $this->getJson("/api/v1/organizer/events/{$event->id}/registrations", [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])->assertForbidden();
    }
}
