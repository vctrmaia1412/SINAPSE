export function Spinner({ className = 'h-8 w-8' }: { className?: string }) {
  return (
    <div
      className={`animate-spin rounded-full border-2 border-brand-200 border-t-brand-600 ${className}`}
      role="status"
      aria-label="Carregando"
    />
  );
}
