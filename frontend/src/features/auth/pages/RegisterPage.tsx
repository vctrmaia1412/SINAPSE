import { useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { useAuth } from '@/features/auth/AuthContext';
import { formatApiErrors } from '@/shared/api/client';
import { ApiError, type UserRole } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Input } from '@/shared/components/Input';
import { Label } from '@/shared/components/Label';

export function RegisterPage() {
  const { user, register, isOrganizer, isParticipant } = useAuth();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [role, setRole] = useState<UserRole>('participant');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (user) {
    if (isOrganizer) return <Navigate to="/organizer" replace />;
    if (isParticipant) return <Navigate to="/participant/events" replace />;
    return <Navigate to="/" replace />;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        role,
      });
    } catch (err) {
      if (err instanceof ApiError) setError(formatApiErrors(err.body) || err.message);
      else setError('Falha ao registrar.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto max-w-md">
      <Card>
        <h1 className="text-xl font-semibold text-slate-900">Criar conta</h1>
        <p className="mt-1 text-sm text-slate-600">Escolha seu papel no sistema.</p>

        {error ? (
          <div className="mt-4">
            <Alert>{error}</Alert>
          </div>
        ) : null}

        <form onSubmit={(e) => void handleSubmit(e)} className="mt-6 space-y-4">
          <div>
            <Label htmlFor="name">Nome</Label>
            <Input id="name" required value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="email">E-mail</Label>
            <Input
              id="email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="role">Papel</Label>
            <select
              id="role"
              value={role}
              onChange={(e) => setRole(e.target.value as UserRole)}
              className="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
            >
              <option value="participant">Participante</option>
              <option value="organizer">Organizador</option>
            </select>
          </div>
          <div>
            <Label htmlFor="password">Senha</Label>
            <Input
              id="password"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="password_confirmation">Confirmar senha</Label>
            <Input
              id="password_confirmation"
              type="password"
              required
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
            />
          </div>
          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? 'Registrando…' : 'Registrar'}
          </Button>
        </form>

        <p className="mt-6 text-center text-sm text-slate-600">
          Já tem conta?{' '}
          <Link to="/login" className="font-medium text-brand-700 hover:underline">
            Entrar
          </Link>
        </p>
      </Card>
    </div>
  );
}
