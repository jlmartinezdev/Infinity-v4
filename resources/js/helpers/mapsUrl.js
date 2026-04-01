/**
 * Extrae latitud y longitud de una URL de Google Maps o texto "lat, lon".
 * Soporta formatos largos (no resuelve URLs cortas maps.app.goo.gl en el frontend).
 *
 * @param {string|null|undefined} url
 * @returns {{ lat: number|null, lon: number|null }}
 */
export function extractLatLonFromMapsUrl(url) {
  if (!url || typeof url !== 'string') return { lat: null, lon: null };
  const s = url.trim();
  let m;
  if ((m = s.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)/))) {
    const lat = parseFloat(m[1]);
    const lon = parseFloat(m[2]);
    if (lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) return { lat, lon };
  }
  if ((m = s.match(/[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/))) {
    const lat = parseFloat(m[1]);
    const lon = parseFloat(m[2]);
    if (lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) return { lat, lon };
  }
  if ((m = s.match(/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*$/))) {
    const lat = parseFloat(m[1]);
    const lon = parseFloat(m[2]);
    if (lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) return { lat, lon };
  }
  return { lat: null, lon: null };
}
