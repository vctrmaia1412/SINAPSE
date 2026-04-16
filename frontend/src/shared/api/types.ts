export type UserRole = 'organizer' | 'participant';

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
}

export interface AuthPayload {
  data: User;
  meta: { token: string };
}

export interface Event {
  id: number;
  organizer_id?: number;
  title: string;
  description: string | null;
  starts_at: string;
  ends_at: string;
  capacity: number;
  status: 'published' | 'cancelled';
  metadata?: Record<string, unknown> | null;
  created_at?: string;
  updated_at?: string;
  confirmed_registrations_count?: number;
  remaining_spots?: number;
}

export interface Paginated<T> {
  data: T[];
  links?: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface EventRegistration {
  id: number;
  status: 'confirmed' | 'cancelled';
  created_at: string;
  updated_at: string;
  event: Event;
}

export interface OrganizerEventRegistration {
  id: number;
  status: 'confirmed' | 'cancelled';
  created_at: string;
  updated_at: string;
  user: Pick<User, 'id' | 'name' | 'email'>;
}

export interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public body: ApiErrorBody,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}
