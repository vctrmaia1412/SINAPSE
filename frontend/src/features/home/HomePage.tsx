import { Link, Navigate } from 'react-router-dom';
import { useAuth } from '@/features/auth/AuthContext';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Spinner } from '@/shared/components/Spinner';

export function HomePage() {
  const { ready, isOrganizer, isParticipant } = useAuth();

  if (!ready) {
    return (
      <div className="flex justify-center py-24">
        <Spinner />
      </div>
    );
  }

  if (isOrganizer) return <Navigate to="/organizer" replace />;
  if (isParticipant) return <Navigate to="/participant/events" replace />;

  return (
    <div className="mx-auto max-w-2xl">
      <Card>
        <h1 className="text-3xl font-semibold text-slate-900">DevEvents</h1>
        <p className="mt-3 text-slate-600">
          Plataforma de demonstração para gestão de eventos técnicos: organizadores publicam eventos e participantes
          se inscrevem com segurança.
        </p>
        <div className="mt-8 flex flex-wrap gap-3">
          <Link to="/login">
            <Button>Entrar</Button>
          </Link>
          <Link to="/register">
            <Button variant="secondary">Criar conta</Button>
          </Link>
          <Link to="/participant/events">
            <Button variant="ghost">Ver catálogo (requer login)</Button>
          </Link>
        </div>
      </Card>
    </div>
  );
}
