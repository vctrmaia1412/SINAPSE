import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  createOrganizerEvent,
  fetchOrganizerEvent,
  updateOrganizerEvent,
} from '@/features/organizer/organizerApi';
import { formatApiErrors } from '@/shared/api/client';
import { ApiError } from '@/shared/api/types';
import { Alert } from '@/shared/components/Alert';
import { Button } from '@/shared/components/Button';
import { Card } from '@/shared/components/Card';
import { Input } from '@/shared/components/Input';
import { Label } from '@/shared/components/Label';
import { PageHeader } from '@/shared/components/PageHeader';
import { Spinner } from '@/shared/components/Spinner';
import { Textarea } from '@/shared/components/Textarea';
import { fromDateTimeLocal, toDateTimeLocal } from '@/shared/lib/dates';

export function OrganizerEventFormPage({ mode }: { mode: 'create' | 'edit' }) {
  const { id } = useParams();
  const navigate = useNavigate();
  const eventId = mode === 'edit' ? Number(id) : NaN;

  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [startsLocal, setStartsLocal] = useState('');
  const [endsLocal, setEndsLocal] = useState('');
  const [capacity, setCapacity] = useState('50');
  const [loading, setLoading] = useState(mode === 'edit');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (mode !== 'edit' || !Number.isFinite(eventId)) return;
    let alive = true;
    (async () => {
      setLoading(true);
      setError(null);
      try {
        const { data } = await fetchOrganizerEvent(eventId);
        if (!alive) return;
        setTitle(data.title);
        setDescription(data.description || '');
        setStartsLocal(toDateTimeLocal(data.starts_at));
        setEndsLocal(toDateTimeLocal(data.ends_at));
        setCapacity(String(data.capacity));
      } catch (e) {
        if (alive) {
          if (e instanceof ApiError) setError(formatApiErrors(e.body) || e.message);
          else setError('Não foi possível carregar o evento.');
        }
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, [mode, eventId]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      const common = {
        title,
        starts_at: fromDateTimeLocal(startsLocal),
        ends_at: fromDateTimeLocal(endsLocal),
        capacity: Number(capacity),
      };
      const desc = description.trim();
      if (mode === 'create') {
        await createOrganizerEvent({
          ...common,
          ...(desc ? { description: desc } : {}),
        });
        navigate('/organizer');
      } else if (Number.isFinite(eventId)) {
        await updateOrganizerEvent(eventId, {
          ...common,
          description: desc || null,
        });
        navigate('/organizer');
      }
    } catch (err) {
      if (err instanceof ApiError) setError(formatApiErrors(err.body) || err.message);
      else setError('Falha ao salvar.');
    } finally {
      setSaving(false);
    }
  }

  if (mode === 'edit' && !Number.isFinite(eventId)) {
    return <Alert>ID inválido.</Alert>;
  }

  if (loading) {
    return (
      <div className="flex justify-center py-24">
        <Spinner />
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={mode === 'create' ? 'Novo evento' : 'Editar evento'}
        actions={
          <Link to="/organizer">
            <Button variant="secondary" className="!text-xs">
              Voltar
            </Button>
          </Link>
        }
      />

      {error ? (
        <div className="mb-4">
          <Alert>{error}</Alert>
        </div>
      ) : null}

      <Card>
        <form onSubmit={(e) => void handleSubmit(e)} className="space-y-4">
          <div>
            <Label htmlFor="title">Título</Label>
            <Input id="title" required value={title} onChange={(e) => setTitle(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="description">Descrição</Label>
            <Textarea
              id="description"
              rows={4}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="starts">Início</Label>
              <Input
                id="starts"
                type="datetime-local"
                required
                value={startsLocal}
                onChange={(e) => setStartsLocal(e.target.value)}
              />
            </div>
            <div>
              <Label htmlFor="ends">Término</Label>
              <Input
                id="ends"
                type="datetime-local"
                required
                value={endsLocal}
                onChange={(e) => setEndsLocal(e.target.value)}
              />
            </div>
          </div>
          <div>
            <Label htmlFor="capacity">Capacidade</Label>
            <Input
              id="capacity"
              type="number"
              min={1}
              required
              value={capacity}
              onChange={(e) => setCapacity(e.target.value)}
            />
          </div>
          <div className="flex gap-3 pt-2">
            <Button type="submit" disabled={saving}>
              {saving
                ? 'Salvando…'
                : mode === 'create'
                  ? 'Criar evento'
                  : 'Atualizar evento'}
            </Button>
            <Button type="button" variant="ghost" onClick={() => navigate('/organizer')}>
              Cancelar
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
