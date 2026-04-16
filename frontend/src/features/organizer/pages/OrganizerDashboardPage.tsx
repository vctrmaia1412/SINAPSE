import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  cancelOrganizerEvent,
  fetchOrganizerEvents,
} from '@/features/organizer/organizerApi';
import { formatApiErrors } from '@/shared/api/client';
import { ApiError, type Event } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { PageHeader } from '@/shared/components/PageHeader';
import { Spinner } from '@/shared/components/Spinner';
import { formatDateTime } from '@/shared/lib/dates';

function registrationsLabel(ev: Event): string {
  if (ev.confirmed_registrations_count === undefined) return '—';
  return `${ev.confirmed_registrations_count} / ${ev.capacity}`;
}

export function OrganizerDashboardPage() {
  const [events, setEvents] = useState<Event[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchOrganizerEvents({ per_page: 50 });
      setEvents(res.data);
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Não foi possível carregar seus eventos.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCancelEvent(id: number) {
    if (!window.confirm('Cancelar este evento? Os participantes verão o status como cancelado.')) return;
    setBusyId(id);
    setError(null);
    try {
      await cancelOrganizerEvent(id);
      await load();
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Falha ao cancelar evento.');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <PageHeader
        title="Meus eventos"
        subtitle="Gerencie eventos que você organiza."
        actions={
          <Link to="/organizer/events/new">
            <Button>Criar evento</Button>
          </Link>
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner />
        </div>
      ) : events.length === 0 ? (
        <Card>
          <p className="text-slate-600">Nenhum evento cadastrado ainda.</p>
          <Link to="/organizer/events/new" className="mt-4 inline-block text-sm font-medium text-brand-700">
            Criar primeiro evento →
          </Link>
        </Card>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="min-w-full min-w-[640px] divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-3 py-3 sm:px-4">Título</th>
                <th className="hidden px-3 py-3 sm:table-cell sm:px-4">Início</th>
                <th className="px-3 py-3 sm:px-4">Inscritos</th>
                <th className="hidden px-3 py-3 md:table-cell md:px-4">Status</th>
                <th className="px-3 py-3 text-right sm:px-4">Ações</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {events.map((ev) => (
                <tr key={ev.id} className="hover:bg-slate-50/80">
                  <td className="px-3 py-3 align-top sm:px-4">
                    <div className="font-medium text-slate-900">{ev.title}</div>
                    <div className="mt-1 text-xs text-slate-500 sm:hidden">
                      {formatDateTime(ev.starts_at)} · {ev.status}
                    </div>
                  </td>
                  <td className="hidden px-3 py-3 text-slate-600 sm:table-cell sm:px-4">
                    {formatDateTime(ev.starts_at)}
                  </td>
                  <td className="whitespace-nowrap px-3 py-3 tabular-nums text-slate-700 sm:px-4">
                    {registrationsLabel(ev)}
                  </td>
                  <td className="hidden px-3 py-3 text-slate-600 md:table-cell md:px-4">{ev.status}</td>
                  <td className="min-w-[12.5rem] px-3 py-3 text-right sm:min-w-[17rem] sm:px-4">
                    <div className="inline-flex max-w-full flex-wrap justify-end gap-2 sm:flex-nowrap">
                      <Link
                        to={`/organizer/events/${ev.id}/registrations`}
                        className="inline-flex shrink-0"
                      >
                        <Button variant="secondary" className="!px-3 !py-1 !text-xs">
                          Inscritos
                        </Button>
                      </Link>
                      <Link to={`/organizer/events/${ev.id}/edit`} className="inline-flex shrink-0">
                        <Button variant="secondary" className="!px-3 !py-1 !text-xs">
                          Editar
                        </Button>
                      </Link>
                      {ev.status === 'published' ? (
                        <span className="inline-flex shrink-0">
                          <Button
                            variant="danger"
                            className="!px-3 !py-1 !text-xs"
                            disabled={busyId === ev.id}
                            onClick={() => void handleCancelEvent(ev.id)}
                          >
                            {busyId === ev.id ? 'Cancelando…' : 'Cancelar'}
                          </Button>
                        </span>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
