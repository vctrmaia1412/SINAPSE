<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithApiTokens;
use Tests\TestCase;

class ParticipantRegistrationApiTest extends TestCase
{
    use InteractsWithApiTokens;

    public function test_participant_lists_only_future_published_events(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $past = Event::factory()->for($organizer, 'organizer')->inThePast()->create();
        $future = Event::factory()->for($organizer, 'organizer')->inTheFuture()->create();

        $response = $this->getJson('/api/v1/participant/events', [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($future->id));
        $this->assertFalse($ids->contains($past->id));
    }

    public function test_participant_events_list_returns_pagination_meta(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        Event::factory()->count(3)->for($organizer, 'organizer')->inTheFuture()->create();

        $response = $this->getJson('/api/v1/participant/events?per_page=2', [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('meta.last_page'));
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_participant_events_catalog_filters_by_title_query(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        Event::factory()->for($organizer, 'organizer')->inTheFuture()->create([
            'title' => 'Workshop Alpha',
        ]);
        Event::factory()->for($organizer, 'organizer')->inTheFuture()->create([
            'title' => 'Meetup Beta',
        ]);

        $response = $this->getJson('/api/v1/participant/events?q=Alpha', [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Workshop Alpha'));
        $this->assertFalse($titles->contains('Meetup Beta'));
    }

    public function test_participant_events_catalog_filters_by_organizer_id(): void
    {
        $alice = User::factory()->organizer()->create();
        $bob = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        Event::factory()->for($alice, 'organizer')->inTheFuture()->create(['title' => 'Alice Event']);
        Event::factory()->for($bob, 'organizer')->inTheFuture()->create(['title' => 'Bob Event']);

        $response = $this->getJson('/api/v1/participant/events?organizer_id='.$alice->id, [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $organizerIds = collect($response->json('data'))->pluck('organizer_id');
        $this->assertTrue($organizerIds->every(fn (int $id): bool => $id === $alice->id));
        $this->assertSame(1, count($response->json('data')));
    }

    public function test_participant_views_published_event_detail(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->inThePast()->create([
            'title' => 'Past But Published',
        ]);

        $this->getJson("/api/v1/participant/events/{$event->id}", [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.title', 'Past But Published');
    }

    public function test_participant_registers_successfully(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addWeek()->addHours(3),
            'capacity' => 10,
            'status' => EventStatus::Published,
        ]);

        $response = $this->postJson("/api/v1/participant/events/{$event->id}/registrations", [], [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.event.id', $event->id);

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_participant_cannot_register_twice(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addWeek()->addHours(3),
            'capacity' => 10,
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ];

        $this->postJson("/api/v1/participant/events/{$event->id}/registrations", [], $headers)
            ->assertCreated();

        $this->postJson("/api/v1/participant/events/{$event->id}/registrations", [], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_participant_cannot_register_on_cancelled_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->cancelled()->inTheFuture()->create();

        $this->postJson("/api/v1/participant/events/{$event->id}/registrations", [], [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_participant_cannot_register_on_past_event(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->inThePast()->create([
            'status' => EventStatus::Published,
        ]);

        $this->postJson("/api/v1/participant/events/{$event->id}/registrations", [], [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_participant_cannot_register_when_event_is_full(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();
        $holder = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addWeek()->addHours(3),
            'capacity' => 1,
            'status' => EventStatus::Published,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $holder->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->postJson("/api/v1/participant/events/{$event->id}/registrations", [], [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    public function test_participant_cancels_own_registration(): void
    {
        $organizer = User::factory()->organizer()->create();
        $participant = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addWeek()->addHours(3),
            'capacity' => 10,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->deleteJson("/api/v1/participant/events/{$event->id}/registration", [], [
            'Authorization' => 'Bearer '.$this->bearer($participant),
            'Accept' => 'application/json',
        ])->assertNoContent();

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_participant_cannot_cancel_another_users_registration(): void
    {
        $organizer = User::factory()->organizer()->create();
        $owner = User::factory()->participant()->create();
        $other = User::factory()->participant()->create();

        $event = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addWeek()->addHours(3),
            'capacity' => 10,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $this->deleteJson("/api/v1/participant/events/{$event->id}/registration", [], [
            'Authorization' => 'Bearer '.$this->bearer($other),
            'Accept' => 'application/json',
        ])->assertNotFound();
    }

    public function test_organizer_is_forbidden_on_all_participant_endpoints(): void
    {
        $organizer = User::factory()->organizer()->create();
        $orgUser = User::factory()->organizer()->create();
        $event = Event::factory()->for($orgUser, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addWeek()->addHours(3),
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$this->bearer($organizer),
            'Accept' => 'application/json',
        ];

        $this->getJson('/api/v1/participant/events', $headers)->assertForbidden();

        $this->getJson("/api/v1/participant/events/{$event->id}", $headers)->assertForbidden();

        $this->postJson("/api/v1/participant/events/{$event->id}/registrations", [], $headers)
            ->assertForbidden();

        $this->deleteJson("/api/v1/participant/events/{$event->id}/registration", [], $headers)
            ->assertForbidden();

        $this->getJson('/api/v1/participant/my-events', $headers)->assertForbidden();
    }

    public function test_my_events_returns_only_authenticated_user_registrations(): void
    {
        $organizer = User::factory()->organizer()->create();
        $alice = User::factory()->participant()->create();
        $bob = User::factory()->participant()->create();

        $eventA = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeek(),
            'ends_at' => Carbon::now()->addWeek()->addHours(3),
        ]);
        $eventB = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeeks(2),
            'ends_at' => Carbon::now()->addWeeks(2)->addHours(3),
        ]);
        $eventBob = Event::factory()->for($organizer, 'organizer')->create([
            'starts_at' => Carbon::now()->addWeeks(3),
            'ends_at' => Carbon::now()->addWeeks(3)->addHours(3),
        ]);

        EventRegistration::query()->create([
            'event_id' => $eventA->id,
            'user_id' => $alice->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        EventRegistration::query()->create([
            'event_id' => $eventB->id,
            'user_id' => $alice->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
        EventRegistration::query()->create([
            'event_id' => $eventBob->id,
            'user_id' => $bob->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $response = $this->getJson('/api/v1/participant/my-events', [
            'Authorization' => 'Bearer '.$this->bearer($alice),
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $eventIds = collect($response->json('data'))->pluck('event.id');
        $this->assertTrue($eventIds->contains($eventA->id));
        $this->assertTrue($eventIds->contains($eventB->id));
        $this->assertFalse($eventIds->contains($eventBob->id));
    }
}
