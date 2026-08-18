/** Banda 5 GHz (UNII-1 … UNII-3) en MHz. */
export const BANDA_5G_MIN = 5150;
export const BANDA_5G_MAX = 5850;
export const BANDA_5G_SPAN = BANDA_5G_MAX - BANDA_5G_MIN;

export const PALETA_AP = [
  '#2563eb',
  '#ea580c',
  '#059669',
  '#c026d3',
  '#ca8a04',
  '#0d9488',
  '#dc2626',
  '#4f46e5',
  '#65a30d',
  '#0891b2',
  '#be185d',
  '#7c3aed',
];

export const REGIONES_5G = [
  { nombre: 'UNII-1', from: 5150, to: 5250 },
  { nombre: 'UNII-2', from: 5250, to: 5350 },
  { nombre: 'UNII-2e', from: 5470, to: 5725 },
  { nombre: 'UNII-3', from: 5725, to: 5850 },
];

export const MARCAS_MHZ = [5150, 5300, 5470, 5600, 5725, 5850];

export function colorAp(apId) {
  const id = Number(apId) || 0;
  const idx = Math.abs(id) % PALETA_AP.length;
  return PALETA_AP[idx];
}

export function parseMhz(valor) {
  if (valor == null || valor === '') {
    return null;
  }
  const n = parseFloat(String(valor).replace(',', '.').replace(/[^\d.]/g, ''));
  return Number.isFinite(n) && n > 0 ? n : null;
}

export function anchoCanalMhz(ap) {
  const directo = parseMhz(ap?.chanbw);
  if (directo && directo >= 5 && directo <= 160) {
    return directo;
  }
  const ieee = String(ap?.extra?.ieee_mode || '').toLowerCase();
  if (ieee.includes('80')) return 80;
  if (ieee.includes('40')) return 40;
  if (ieee.includes('20')) return 20;
  if (ieee.includes('10')) return 10;
  return 20;
}

export function pctBanda(mhz) {
  const clipped = Math.min(BANDA_5G_MAX, Math.max(BANDA_5G_MIN, mhz));
  return ((clipped - BANDA_5G_MIN) / BANDA_5G_SPAN) * 100;
}

/**
 * @returns {{ ap: object, color: string, center: number, bw: number, from: number, to: number, left: number, width: number }|null}
 */
export function bloqueEspectro(ap) {
  const center = parseMhz(ap?.frecuencia);
  if (center == null || center < 4900 || center > 5900) {
    return null;
  }
  const bw = anchoCanalMhz(ap);
  const from = center - bw / 2;
  const to = center + bw / 2;
  const visFrom = Math.max(BANDA_5G_MIN, from);
  const visTo = Math.min(BANDA_5G_MAX, to);
  if (visTo <= visFrom) {
    return null;
  }
  return {
    ap,
    color: colorAp(ap.ap_id),
    center,
    bw,
    from,
    to,
    left: pctBanda(visFrom),
    width: pctBanda(visTo) - pctBanda(visFrom),
  };
}

export function bloquesDeAps(aps) {
  return (Array.isArray(aps) ? aps : [])
    .map(bloqueEspectro)
    .filter(Boolean)
    .sort((a, b) => a.from - b.from);
}

function mergeOcupado(bloques) {
  const sorted = bloques
    .map((b) => ({
      from: Math.max(BANDA_5G_MIN, b.from),
      to: Math.min(BANDA_5G_MAX, b.to),
    }))
    .filter((b) => b.to > b.from)
    .sort((a, b) => a.from - b.from);

  const out = [];
  for (const iv of sorted) {
    const last = out[out.length - 1];
    if (!last || iv.from > last.to) {
      out.push({ ...iv });
    } else {
      last.to = Math.max(last.to, iv.to);
    }
  }
  return out;
}

export function resumenLibre(bloques) {
  const ocupado = mergeOcupado(bloques);
  const mhzOcupado = ocupado.reduce((acc, iv) => acc + (iv.to - iv.from), 0);
  const mhzLibre = Math.max(0, BANDA_5G_SPAN - mhzOcupado);
  return {
    mhzOcupado: Math.round(mhzOcupado),
    mhzLibre: Math.round(mhzLibre),
    pctLibre: Math.round((mhzLibre / BANDA_5G_SPAN) * 100),
    ocupado,
  };
}

export function solapes(bloques) {
  const pares = [];
  for (let i = 0; i < bloques.length; i += 1) {
    for (let j = i + 1; j < bloques.length; j += 1) {
      const a = bloques[i];
      const b = bloques[j];
      const from = Math.max(a.from, b.from);
      const to = Math.min(a.to, b.to);
      if (to > from) {
        pares.push({
          a,
          b,
          from,
          to,
          mhz: Math.round(to - from),
        });
      }
    }
  }
  return pares;
}
