<?php

namespace App\Repositories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EventRepository
{
    public function paginateOrganizerEvents(User $organizer, int $perPage): LengthAwarePaginator
    {
        return $organizer->organizedEvents()
            ->withCount('confirmedRegistrations')
            ->orderByDesc('starts_at')
            ->paginate($perPage);
    }

    /**
     * @param  array{q?: string|null, organizer_id?: int|null, starts_from?: string|null, starts_until?: string|null}  $filters
     */
    public function paginatePublishedFutureCatalog(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $query = Event::query()
            ->where('status', EventStatus::Published)
            ->where('starts_at', '>', now())
            ->withCount('confirmedRegistrations');

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('title', 'like', $like);
        }

        if (! empty($filters['organizer_id'])) {
            $query->where('organizer_id', (int) $filters['organizer_id']);
        }

        if (! empty($filters['starts_from'])) {
            $query->where('starts_at', '>=', Carbon::parse($filters['starts_from']));
        }

        if (! empty($filters['starts_until'])) {
            $query->where('starts_at', '<=', Carbon::parse($filters['starts_until']));
        }

        return $query->orderBy('starts_at')->paginate($perPage);
    }
}
