import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
  fetchOrganizerEvent,
  fetchOrganizerEventRegistrations,
} from '@/features/organizer/organizerApi';
import { formatApiErrors } from '@/shared/api/client';
import { ApiError, type Event, type OrganizerEventRegistration } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { PageHeader } from '@/shared/components/PageHeader';
import { Spinner } from '@/shared/components/Spinner';
import { formatDateTime } from '@/shared/lib/dates';

const PER_PAGE = 15;

export function OrganizerEventRegistrationsPage() {
  const { id } = useParams();
  const eventId = Number(id);

  const [event, setEvent] = useState<Event | null>(null);
  const [rows, setRows] = useState<OrganizerEventRegistration[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!Number.isFinite(eventId)) {
      setLoading(false);
      setError('ID do evento inválido.');
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [{ data: ev }, regRes] = await Promise.all([
        fetchOrganizerEvent(eventId),
        fetchOrganizerEventRegistrations(eventId, { per_page: PER_PAGE, page }),
      ]);
      setEvent(ev);
      setRows(regRes.data);
      setLastPage(regRes.meta?.last_page ?? 1);
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Não foi possível carregar as inscrições.');
      setEvent(null);
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [eventId, page]);

  useEffect(() => {
    void load();
  }, [load]);

  if (!Number.isFinite(eventId)) {
    return <Alert>ID inválido.</Alert>;
  }

  return (
    <div>
      <PageHeader
        title={event ? `Inscritos — ${event.title}` : 'Inscritos no evento'}
        subtitle="Participantes com inscrição registrada neste evento."
        actions={
          <Link to="/organizer">
            <Button variant="secondary" className="!text-xs">
              Voltar ao painel
            </Button>
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
      ) : !event ? null : rows.length === 0 ? (
        <Card>
          <p className="text-slate-600">Nenhuma inscrição neste evento ainda.</p>
          <Link
            to={`/organizer/events/${eventId}/edit`}
            className="mt-4 inline-block text-sm font-medium text-brand-700 hover:underline"
          >
            Editar evento
          </Link>
        </Card>
      ) : (
        <>
          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3">Participante</th>
                  <th className="px-4 py-3">E-mail</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Inscrito em</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {rows.map((row) => (
                  <tr key={row.id} className="hover:bg-slate-50/80">
                    <td className="px-4 py-3 font-medium text-slate-900">{row.user.name}</td>
                    <td className="px-4 py-3 text-slate-600">{row.user.email}</td>
                    <td className="px-4 py-3 text-slate-600">{row.status}</td>
                    <td className="px-4 py-3 text-slate-600">{formatDateTime(row.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {lastPage > 1 ? (
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
              <p className="text-sm text-slate-600">
                Página {page} de {lastPage}
              </p>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  className="!text-xs"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Anterior
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  className="!text-xs"
                  disabled={page >= lastPage}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Próxima
                </Button>
              </div>
            </div>
          ) : null}
        </>
      )}
    </div>
  );
}
