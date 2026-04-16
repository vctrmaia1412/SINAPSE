import { apiRequest } from '@/shared/api/client';
import type { AuthPayload, User } from '@/shared/api/types';

export async function loginRequest(email: string, password: string): Promise<AuthPayload> {
  return apiRequest<AuthPayload>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
    token: null,
  });
}

export async function registerRequest(payload: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: 'organizer' | 'participant';
}): Promise<AuthPayload> {
  return apiRequest<AuthPayload>('/auth/register', {
    method: 'POST',
    body: JSON.stringify(payload),
    token: null,
  });
}

export async function meRequest(token: string): Promise<{ data: User }> {
  return apiRequest<{ data: User }>('/me', { method: 'GET', token });
}

export async function logoutRequest(token: string): Promise<void> {
  await apiRequest<void>('/auth/logout', { method: 'POST', token });
}
