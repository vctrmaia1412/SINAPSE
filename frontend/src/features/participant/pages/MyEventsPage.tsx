import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { cancelMyRegistration, fetchMyEvents } from '@/features/participant/participantApi';
import { formatApiErrors } from '@/shared/api/client';
import { ApiError, type EventRegistration } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { PageHeader } from '@/shared/components/PageHeader';
import { Spinner } from '@/shared/components/Spinner';
import { formatDateTime } from '@/shared/lib/dates';

export function MyEventsPage() {
  const [items, setItems] = useState<EventRegistration[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchMyEvents({ per_page: 100 });
      setItems(res.data);
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Não foi possível carregar suas inscrições.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function handleCancel(eventId: number) {
    setBusyId(eventId);
    setError(null);
    try {
      await cancelMyRegistration(eventId);
      await load();
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Falha ao cancelar inscrição.');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <PageHeader title="Meus eventos" subtitle="Inscrições confirmadas e histórico recente." />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner />
        </div>
      ) : items.length === 0 ? (
        <Card>
          <p className="text-slate-600">Você ainda não possui inscrições.</p>
          <Link to="/participant/events" className="mt-4 inline-block text-sm font-medium text-brand-700">
            Explorar eventos →
          </Link>
        </Card>
      ) : (
        <ul className="space-y-4">
          {items.map((row) => (
            <li key={row.id}>
              <Card>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <h2 className="text-lg font-semibold text-slate-900">{row.event.title}</h2>
                    <p className="mt-1 text-sm text-slate-600">
                      {formatDateTime(row.event.starts_at)} · Status:{' '}
                      <span className="font-medium">{row.status}</span>
                    </p>
                    <Link
                      to={`/participant/events/${row.event.id}`}
                      className="mt-2 inline-block text-sm text-brand-700 hover:underline"
                    >
                      Abrir evento
                    </Link>
                  </div>
                  {row.status === 'confirmed' ? (
                    <Button
                      variant="danger"
                      className="shrink-0 !text-xs"
                      disabled={busyId === row.event.id}
                      onClick={() => void handleCancel(row.event.id)}
                    >
                      {busyId === row.event.id ? 'Cancelando…' : 'Cancelar inscrição'}
                    </Button>
                  ) : null}
                </div>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
