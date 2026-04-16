export function safeInternalPath(path: string | undefined | null): string | null {
  if (!path || typeof path !== 'string') return null;
  if (!path.startsWith('/') || path.startsWith('//')) return null;
  if (path.includes('://')) return null;
  return path;
}
