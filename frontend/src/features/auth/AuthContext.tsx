import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import {
  AUTH_USER_STORAGE_KEY,
  clearAuthSession,
  getStoredToken,
  setStoredToken,
} from '@/shared/api/client';
import type { User, UserRole } from '@/shared/api/types';
import { loginRequest, logoutRequest, meRequest, registerRequest } from './authApi';

function readStoredUser(): User | null {
  try {
    const raw = localStorage.getItem(AUTH_USER_STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

function writeStoredUser(user: User | null): void {
  if (user) localStorage.setItem(AUTH_USER_STORAGE_KEY, JSON.stringify(user));
  else localStorage.removeItem(AUTH_USER_STORAGE_KEY);
}

type AuthContextValue = {
  user: User | null;
  token: string | null;
  ready: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (payload: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: UserRole;
  }) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  isOrganizer: boolean;
  isParticipant: boolean;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);

  const applySession = useCallback((u: User, t: string) => {
    setUser(u);
    setToken(t);
    setStoredToken(t);
    writeStoredUser(u);
  }, []);

  const clearSession = useCallback(() => {
    setUser(null);
    setToken(null);
    clearAuthSession();
  }, []);

  const refreshUser = useCallback(async () => {
    const t = getStoredToken();
    if (!t) {
      clearSession();
      return;
    }
    const { data } = await meRequest(t);
    setUser(data);
    writeStoredUser(data);
  }, [clearSession]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      const t = getStoredToken();
      const cached = readStoredUser();
      if (t && cached) {
        setToken(t);
        setUser(cached);
      }
      if (t) {
        try {
          const { data } = await meRequest(t);
          if (!cancelled) {
            setUser(data);
            writeStoredUser(data);
            setToken(t);
          }
        } catch {
          if (!cancelled) clearSession();
        }
      } else if (!cancelled) {
        clearSession();
      }
      if (!cancelled) setReady(true);
    })();
    return () => {
      cancelled = true;
    };
  }, [clearSession]);

  const login = useCallback(
    async (email: string, password: string) => {
      const res = await loginRequest(email, password);
      applySession(res.data, res.meta.token);
    },
    [applySession],
  );

  const register = useCallback(
    async (payload: {
      name: string;
      email: string;
      password: string;
      password_confirmation: string;
      role: UserRole;
    }) => {
      const res = await registerRequest(payload);
      applySession(res.data, res.meta.token);
    },
    [applySession],
  );

  const logout = useCallback(async () => {
    const t = getStoredToken();
    if (t) {
      try {
        await logoutRequest(t);
      } catch {}
    }
    clearSession();
  }, [clearSession]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      ready,
      login,
      register,
      logout,
      refreshUser,
      isOrganizer: user?.role === 'organizer',
      isParticipant: user?.role === 'participant',
    }),
    [user, token, ready, login, register, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}

export { clearAuthSession as clearAuthStorage } from '@/shared/api/client';
