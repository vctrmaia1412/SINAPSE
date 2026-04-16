<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOrganizer();
    }

    public function view(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $user->id === $event->organizer_id;
    }

    public function create(User $user): bool
    {
        return $user->isOrganizer();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $user->id === $event->organizer_id;
    }

    public function cancel(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function viewRegistrations(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return false;
    }
}
