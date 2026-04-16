import { apiRequest } from '@/shared/api/client';
import type { Event, OrganizerEventRegistration, Paginated } from '@/shared/api/types';

export async function fetchOrganizerEvents(params?: { per_page?: number }): Promise<Paginated<Event>> {
  const q = new URLSearchParams();
  if (params?.per_page) q.set('per_page', String(params.per_page));
  const qs = q.toString();
  return apiRequest<Paginated<Event>>(`/organizer/events${qs ? `?${qs}` : ''}`);
}

export async function fetchOrganizerEvent(id: number): Promise<{ data: Event }> {
  return apiRequest<{ data: Event }>(`/organizer/events/${encodeURIComponent(String(id))}`);
}

export async function fetchOrganizerEventRegistrations(
  eventId: number,
  params?: { per_page?: number; page?: number },
): Promise<Paginated<OrganizerEventRegistration>> {
  const q = new URLSearchParams();
  if (params?.per_page) q.set('per_page', String(params.per_page));
  if (params?.page != null && params.page >= 1) q.set('page', String(params.page));
  const qs = q.toString();
  const id = encodeURIComponent(String(eventId));
  return apiRequest<Paginated<OrganizerEventRegistration>>(
    `/organizer/events/${id}/registrations${qs ? `?${qs}` : ''}`,
  );
}

export async function createOrganizerEvent(body: {
  title: string;
  description?: string;
  starts_at: string;
  ends_at: string;
  capacity: number;
}): Promise<{ data: Event }> {
  return apiRequest<{ data: Event }>('/organizer/events', {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

export async function updateOrganizerEvent(
  id: number,
  body: Partial<{
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    capacity: number;
    status: 'published' | 'cancelled';
  }>,
): Promise<{ data: Event }> {
  return apiRequest<{ data: Event }>(`/organizer/events/${encodeURIComponent(String(id))}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  });
}

export async function cancelOrganizerEvent(id: number): Promise<{ data: Event }> {
  return apiRequest<{ data: Event }>(`/organizer/events/${encodeURIComponent(String(id))}/cancel`, {
    method: 'POST',
  });
}
