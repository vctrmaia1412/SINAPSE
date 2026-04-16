import { ApiError, type ApiErrorBody } from './types';

const TOKEN_KEY = 'devevents_token';

export const AUTH_USER_STORAGE_KEY = 'devevents_user';

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setStoredToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export function clearAuthSession(): void {
  setStoredToken(null);
  localStorage.removeItem(AUTH_USER_STORAGE_KEY);
}

function normalizeApiBase(raw: string | undefined): string {
  const t = (raw ?? '').trim();
  const fallback = '/api/v1';
  if (!t) return fallback;
  if (t.startsWith('http://') || t.startsWith('https://')) {
    return t.replace(/\/+$/, '') || fallback;
  }
  const withSlash = t.startsWith('/') ? t : `/${t}`;
  return withSlash.replace(/\/+$/, '') || fallback;
}

const baseUrl = normalizeApiBase(import.meta.env.VITE_API_BASE_URL);

function buildUrl(path: string): string {
  const p = path.startsWith('/') ? path : `/${path}`;
  return `${baseUrl}${p}`;
}

export async function apiRequest<T>(
  path: string,
  init: RequestInit & { token?: string | null } = {},
): Promise<T> {
  const { token = getStoredToken(), ...rest } = init;
  const headers = new Headers(rest.headers);
  if (!headers.has('Accept')) headers.set('Accept', 'application/json');
  if (rest.body && !(rest.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const res = await fetch(buildUrl(path), { ...rest, headers });

  if (res.status === 204) {
    return undefined as T;
  }

  let body: unknown = {};
  const text = await res.text();
  if (text) {
    try {
      body = JSON.parse(text) as unknown;
    } catch {
      body = {};
    }
  }

  if (!res.ok) {
    const b = body as ApiErrorBody;
    const msg =
      b.message ||
      (b.errors && Object.values(b.errors).flat()[0]) ||
      res.statusText ||
      'Erro na requisição';

    if (res.status === 401 && token) {
      clearAuthSession();
      window.location.assign(`${window.location.origin}/login`);
    }

    throw new ApiError(msg, res.status, b);
  }

  return body as T;
}

export function formatApiErrors(body: ApiErrorBody): string {
  if (body.errors) {
    return Object.entries(body.errors)
      .map(([k, v]) => `${k}: ${v.join(', ')}`)
      .join('\n');
  }
  return body.message || 'Erro desconhecido';
}
