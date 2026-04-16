import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
  cancelMyRegistration,
  fetchMyEvents,
  fetchParticipantEvent,
  registerForEvent,
} from '@/features/participant/participantApi';
import { formatApiErrors } from '@/shared/api/client';
import { ApiError, type Event } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { PageHeader } from '@/shared/components/PageHeader';
import { Spinner } from '@/shared/components/Spinner';
import { formatDateTime } from '@/shared/lib/dates';

export function ParticipantEventDetailPage() {
  const { id } = useParams();
  const eventId = Number(id);

  const [event, setEvent] = useState<Event | null>(null);
  const [registeredConfirmed, setRegisteredConfirmed] = useState(false);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!Number.isFinite(eventId)) return;
    setLoading(true);
    setError(null);
    try {
      const [{ data: ev }, my] = await Promise.all([
        fetchParticipantEvent(eventId),
        fetchMyEvents({ per_page: 100 }),
      ]);
      setEvent(ev);
      const mine = my.data.find((r) => r.event.id === eventId && r.status === 'confirmed');
      setRegisteredConfirmed(!!mine);
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Evento não encontrado ou indisponível.');
      setEvent(null);
    } finally {
      setLoading(false);
    }
  }, [eventId]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleRegister() {
    if (!Number.isFinite(eventId)) return;
    setActionLoading(true);
    setError(null);
    setSuccess(null);
    try {
      await registerForEvent(eventId);
      setSuccess('Inscrição confirmada.');
      setRegisteredConfirmed(true);
      await load();
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Falha ao inscrever.');
    } finally {
      setActionLoading(false);
    }
  }

  async function handleCancel() {
    if (!Number.isFinite(eventId)) return;
    setActionLoading(true);
    setError(null);
    setSuccess(null);
    try {
      await cancelMyRegistration(eventId);
      setSuccess('Inscrição cancelada.');
      setRegisteredConfirmed(false);
      await load();
    } catch (e) {
      if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
      else setError('Falha ao cancelar inscrição.');
    } finally {
      setActionLoading(false);
    }
  }

  if (!Number.isFinite(eventId)) {
    return <Alert>ID inválido.</Alert>;
  }

  if (loading) {
    return (
      <div className="flex justify-center py-24">
        <Spinner />
      </div>
    );
  }

  if (!event) {
    return (
      <div>
        <Alert>{error || 'Evento não encontrado.'}</Alert>
        <Link to="/participant/events" className="mt-4 inline-block text-sm text-brand-700">
          ← Voltar à lista
        </Link>
      </div>
    );
  }

  const isPast = new Date(event.starts_at) <= new Date();
  const isCancelled = event.status === 'cancelled';
  const full =
    event.remaining_spots !== undefined ? event.remaining_spots <= 0 && !registeredConfirmed : false;

  return (
    <div>
      <PageHeader
        title={event.title}
        actions={
          <Link to="/participant/events">
            <Button variant="secondary" className="!text-xs">
              ← Lista
            </Button>
          </Link>
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}
      {success ? (
        <div className="mb-4">
          <Alert variant="success">{success}</Alert>
        </div>
      ) : null}

      <Card>
        <dl className="grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="font-medium text-slate-700">Início</dt>
            <dd className="text-slate-600">{formatDateTime(event.starts_at)}</dd>
          </div>
          <div>
            <dt className="font-medium text-slate-700">Término</dt>
            <dd className="text-slate-600">{formatDateTime(event.ends_at)}</dd>
          </div>
          <div>
            <dt className="font-medium text-slate-700">Capacidade</dt>
            <dd className="text-slate-600">{event.capacity}</dd>
          </div>
          {event.confirmed_registrations_count !== undefined ? (
            <div>
              <dt className="font-medium text-slate-700">Inscritos</dt>
              <dd className="text-slate-600">
                {event.confirmed_registrations_count} / {event.capacity}
              </dd>
            </div>
          ) : null}
          {event.remaining_spots !== undefined ? (
            <div>
              <dt className="font-medium text-slate-700">Vagas livres</dt>
              <dd className="text-slate-600">{event.remaining_spots}</dd>
            </div>
          ) : null}
          <div>
            <dt className="font-medium text-slate-700">Status</dt>
            <dd className="text-slate-600">{event.status}</dd>
          </div>
        </dl>
        {event.description ? (
          <div className="mt-6 border-t border-slate-100 pt-6">
            <h2 className="text-sm font-medium text-slate-800">Descrição</h2>
            <p className="mt-2 whitespace-pre-wrap text-sm text-slate-600">{event.description}</p>
          </div>
        ) : null}

        <div className="mt-8 flex flex-wrap gap-3 border-t border-slate-100 pt-6">
          {registeredConfirmed ? (
            <Button variant="danger" disabled={actionLoading} onClick={() => void handleCancel()}>
              {actionLoading ? 'Cancelando…' : 'Cancelar inscrição'}
            </Button>
          ) : (
            <Button
              disabled={
                actionLoading || isPast || isCancelled || full || event.status !== 'published'
              }
              onClick={() => void handleRegister()}
            >
              {actionLoading ? 'Processando…' : 'Inscrever-se'}
            </Button>
          )}
          {isPast ? (
            <span className="self-center text-sm text-slate-500">Evento já iniciou ou encerrou.</span>
          ) : null}
          {isCancelled ? (
            <span className="self-center text-sm text-slate-500">Evento cancelado.</span>
          ) : null}
          {full && !registeredConfirmed ? (
            <span className="self-center text-sm text-slate-500">Lotado.</span>
          ) : null}
        </div>
      </Card>
    </div>
  );
}
