export function formatearCaidoHace(iso, nowMs) {
  if (!iso) return null;
  const t = new Date(iso).getTime();
  if (Number.isNaN(t)) return null;
  const segs = Math.max(0, Math.floor(((nowMs || Date.now()) - t) / 1000));
  if (segs < 60) return '< 1 min';
  const dias = Math.floor(segs / 86400);
  const horas = Math.floor((segs % 86400) / 3600);
  const minutos = Math.floor((segs % 3600) / 60);
  const partes = [];
  if (dias > 0) partes.push(`${dias}d`);
  if (horas > 0 || dias > 0) partes.push(`${horas}h`);
  partes.push(`${minutos}m`);
  return partes.join(' ');
}
