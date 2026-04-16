<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Event
 */
class ParticipantEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasCount = $this->resource->offsetExists('confirmed_registrations_count');
        $confirmed = $hasCount ? (int) $this->resource->getAttribute('confirmed_registrations_count') : null;

        return [
            'id' => $this->id,
            'organizer_id' => $this->organizer_id,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'metadata' => $this->metadata,
            'confirmed_registrations_count' => $this->when($hasCount, $confirmed),
            'remaining_spots' => $this->when($hasCount, max(0, $this->capacity - (int) $confirmed)),
        ];
    }
}
