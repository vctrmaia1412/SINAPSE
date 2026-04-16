import { Link, NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '@/features/auth/AuthContext';
import { Button } from './Button';

const navClass = ({ isActive }: { isActive: boolean }) =>
  `rounded-md px-3 py-2 text-sm font-medium transition ${isActive ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'}`;

export function AppShell() {
  const { user, logout, isOrganizer, isParticipant } = useAuth();

  return (
    <div className="min-h-screen flex flex-col">
      <header className="border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4">
          <Link to="/" className="text-lg font-semibold text-brand-700">
            DevEvents
          </Link>
          <nav className="flex flex-wrap items-center gap-1">
            {isParticipant ? (
              <>
                <NavLink to="/participant/events" className={navClass}>
                  Eventos
                </NavLink>
                <NavLink to="/participant/my-events" className={navClass}>
                  Meus eventos
                </NavLink>
              </>
            ) : null}
            {isOrganizer ? (
              <NavLink to="/organizer" className={navClass}>
                Painel
              </NavLink>
            ) : null}
          </nav>
          <div className="flex items-center gap-3">
            {user ? (
              <span className="hidden text-sm text-slate-600 sm:inline">
                {user.name}{' '}
                <span className="text-slate-400">({user.role})</span>
              </span>
            ) : null}
            {user ? (
              <Button type="button" variant="secondary" className="!py-1.5 !text-xs" onClick={() => void logout()}>
                Sair
              </Button>
            ) : (
              <>
                <Link to="/login">
                  <Button variant="ghost" className="!py-1.5 !text-xs">
                    Entrar
                  </Button>
                </Link>
                <Link to="/register">
                  <Button className="!py-1.5 !text-xs">Registrar</Button>
                </Link>
              </>
            )}
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <Outlet />
      </main>
      <footer className="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
        DevEvents — demonstração local
      </footer>
    </div>
  );
}
