import type { ReactNode } from 'react';

export function Alert({
  variant = 'error',
  className = '',
  children,
}: {
  variant?: 'error' | 'success' | 'info';
  className?: string;
  children: ReactNode;
}) {
  const styles =
    variant === 'success'
      ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
      : variant === 'info'
        ? 'border-sky-200 bg-sky-50 text-sky-900'
        : 'border-red-200 bg-red-50 text-red-900';

  return (
    <div className={`rounded-lg border px-4 py-3 text-sm ${styles} ${className}`} role="alert">
      {children}
    </div>
  );
}
