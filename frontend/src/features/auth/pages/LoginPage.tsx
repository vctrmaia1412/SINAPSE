import { useState } from 'react';
import { Link, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/features/auth/AuthContext';
import { formatApiErrors } from '@/shared/api/client';
import { safeInternalPath } from '@/shared/lib/navigation';
import { ApiError } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Input } from '@/shared/components/Input';
import { Label } from '@/shared/components/Label';

export function LoginPage() {
  const { user, login, isOrganizer, isParticipant } = useAuth();
  const location = useLocation();
  const from = (location.state as { from?: string } | null)?.from;

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (user) {
    if (isOrganizer) return <Navigate to="/organizer" replace />;
    if (isParticipant) {
      const next = safeInternalPath(from) ?? '/participant/events';
      return <Navigate to={next} replace />;
    }
    return <Navigate to="/" replace />;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await login(email, password);
    } catch (err) {
      if (err instanceof ApiError) setError(formatApiErrors(err.body) || err.message);
      else setError('Falha ao entrar.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto max-w-md">
      <Card>
        <h1 className="text-xl font-semibold text-slate-900">Entrar</h1>
        <p className="mt-1 text-sm text-slate-600">Use sua conta DevEvents.</p>

        {error ? (
          <div className="mt-4">
            <Alert>{error}</Alert>
          </div>
        ) : null}

        <form onSubmit={(e) => void handleSubmit(e)} className="mt-6 space-y-4">
          <div>
            <Label htmlFor="email">E-mail</Label>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="password">Senha</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>
          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? 'Entrando…' : 'Entrar'}
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-slate-600">
          Não tem conta?{' '}
          <Link to="/register" className="font-medium text-brand-700 hover:underline">
            Registrar
          </Link>
        </p>
      </Card>
    </div>
  );
}
