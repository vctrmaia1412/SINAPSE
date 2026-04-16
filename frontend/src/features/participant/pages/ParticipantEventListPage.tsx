import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { fetchParticipantEvents } from '@/features/participant/participantApi';
import { formatApiErrors } from '@/shared/api/client';
import { ApiError, type Event } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { PageHeader } from '@/shared/components/PageHeader';
import { Spinner } from '@/shared/components/Spinner';
import { formatDateTime } from '@/shared/lib/dates';

const PER_PAGE = 8;

export function ParticipantEventListPage() {
  const [events, setEvents] = useState<Event[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [titleDraft, setTitleDraft] = useState('');
  const [titleQuery, setTitleQuery] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetchParticipantEvents({
        per_page: PER_PAGE,
        page,
        q: titleQuery || undefined,
      });
      setEvents(res.data);
      setLastPage(res.meta?.last_page ?? 1);
      setTotal(res.meta?.total ?? res.data.length);
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Não foi possível carregar os eventos.');
      setEvents([]);
    } finally {
      setLoading(false);
    }
  }, [page, titleQuery]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div>
      <PageHeader
        title="Eventos futuros"
        subtitle="Inscreva-se em workshops e meetups publicados."
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Card className="mb-6">
        <form
          className="flex flex-col gap-3 sm:flex-row sm:items-end"
          onSubmit={(e) => {
            e.preventDefault();
            setTitleQuery(titleDraft.trim());
            setPage(1);
          }}
        >
          <div className="min-w-0 flex-1">
            <label htmlFor="event-search" className="mb-1 block text-xs font-medium text-slate-600">
              Buscar por título
            </label>
            <input
              id="event-search"
              type="search"
              value={titleDraft}
              onChange={(e) => setTitleDraft(e.target.value)}
              placeholder="Ex.: Laravel, workshop…"
              className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
          </div>
          <div className="flex shrink-0 gap-2">
            <Button type="submit" className="!text-sm">
              Filtrar
            </Button>
            <Button
              type="button"
              variant="secondary"
              className="!text-sm"
              disabled={!titleDraft && !titleQuery}
              onClick={() => {
                setTitleDraft('');
                setTitleQuery('');
                setPage(1);
              }}
            >
              Limpar
            </Button>
          </div>
        </form>
      </Card>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner />
        </div>
      ) : events.length === 0 ? (
        <Card>
          <p className="text-slate-600">Nenhum evento futuro disponível no momento.</p>
        </Card>
      ) : (
        <>
          <ul className="grid gap-4 sm:grid-cols-2">
            {events.map((ev) => (
              <li key={ev.id}>
                <Card className="h-full transition hover:border-brand-200">
                  <h2 className="text-lg font-semibold text-slate-900">{ev.title}</h2>
                  <p className="mt-2 line-clamp-2 text-sm text-slate-600">{ev.description || '—'}</p>
                  <dl className="mt-4 space-y-1 text-xs text-slate-500">
                    <div>
                      <dt className="inline font-medium text-slate-700">Início: </dt>
                      <dd className="inline">{formatDateTime(ev.starts_at)}</dd>
                    </div>
                    {ev.confirmed_registrations_count !== undefined ? (
                      <div>
                        <dt className="inline font-medium text-slate-700">Inscritos: </dt>
                        <dd className="inline">
                          {ev.confirmed_registrations_count} / {ev.capacity}
                        </dd>
                      </div>
                    ) : null}
                    {ev.remaining_spots !== undefined ? (
                      <div>
                        <dt className="inline font-medium text-slate-700">Vagas livres: </dt>
                        <dd className="inline">{ev.remaining_spots}</dd>
                      </div>
                    ) : null}
                  </dl>
                  <Link
                    to={`/participant/events/${ev.id}`}
                    className="mt-4 inline-block text-sm font-medium text-brand-700 hover:underline"
                  >
                    Ver detalhes →
                  </Link>
                </Card>
              </li>
            ))}
          </ul>

          {lastPage > 1 ? (
            <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-6">
              <p className="text-sm text-slate-600">
                Página {page} de {lastPage}
                {total > 0 ? ` · ${total} evento${total === 1 ? '' : 's'}` : null}
              </p>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  className="!text-xs"
                  disabled={page <= 1 || loading}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Anterior
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  className="!text-xs"
                  disabled={page >= lastPage || loading}
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
