import { apiRequest } from '@/shared/api/client';
import type { Event, EventRegistration, Paginated } from '@/shared/api/types';

export async function fetchParticipantEvents(params?: {
  per_page?: number;
  page?: number;
  q?: string;
  organizer_id?: number;
  starts_from?: string;
  starts_until?: string;
}): Promise<Paginated<Event>> {
  const q = new URLSearchParams();
  if (params?.per_page) q.set('per_page', String(params.per_page));
  if (params?.page) q.set('page', String(params.page));
  if (params?.q?.trim()) q.set('q', params.q.trim());
  if (params?.organizer_id != null) q.set('organizer_id', String(params.organizer_id));
  if (params?.starts_from) q.set('starts_from', params.starts_from);
  if (params?.starts_until) q.set('starts_until', params.starts_until);
  const qs = q.toString();
  return apiRequest<Paginated<Event>>(`/participant/events${qs ? `?${qs}` : ''}`);
}

export async function fetchParticipantEvent(id: number): Promise<{ data: Event }> {
  return apiRequest<{ data: Event }>(`/participant/events/${id}`);
}

export async function registerForEvent(eventId: number): Promise<{ data: EventRegistration }> {
  return apiRequest<{ data: EventRegistration }>(`/participant/events/${eventId}/registrations`, {
    method: 'POST',
  });
}

export async function cancelMyRegistration(eventId: number): Promise<void> {
  await apiRequest<void>(`/participant/events/${eventId}/registration`, {
    method: 'DELETE',
  });
}

export async function fetchMyEvents(params?: { per_page?: number }): Promise<Paginated<EventRegistration>> {
  const q = new URLSearchParams();
  if (params?.per_page) q.set('per_page', String(params.per_page));
  const qs = q.toString();
  return apiRequest<Paginated<EventRegistration>>(`/participant/my-events${qs ? `?${qs}` : ''}`);
}
