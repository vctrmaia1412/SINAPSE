import type { InputHTMLAttributes } from 'react';

export function Input({
  className = '',
  id,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { id: string }) {
  return (
    <input
      id={id}
      className={`block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 ${className}`}
      {...props}
    />
  );
}
