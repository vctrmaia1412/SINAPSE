import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '@/features/auth/AuthContext';
import { Spinner } from '@/shared/components/Spinner';
import type { UserRole } from '@/shared/api/types';

function BootSpinner() {
  return (
    <div className="flex min-h-[40vh] items-center justify-center">
      <Spinner />
    </div>
  );
}

export function RequireAuth() {
  const { user, ready } = useAuth();
  const location = useLocation();

  if (!ready) return <BootSpinner />;
  if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;

  return <Outlet />;
}

export function RequireRole({ role }: { role: UserRole }) {
  const { user, ready } = useAuth();

  if (!ready) return <BootSpinner />;
  if (!user || user.role !== role) return <Navigate to="/" replace />;

  return <Outlet />;
}
