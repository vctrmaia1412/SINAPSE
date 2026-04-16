import type { ReactNode } from 'react';

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={`rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5 ${className}`}
    >
      {children}
    </div>
  );
}
