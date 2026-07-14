// The study-site mapping — the ONLY place that knows csnades.gg's URL scheme. The
// backend stores semantics (map + nade_type); this module renders them as links, the
// same boundary rule as codes → sentences. Silent coupling accepted: if csnades
// restructures routes, links 404 with no error anywhere — swap this module, not data.
// Paths verified 2026-07: smokes are the map root; HE is "hegrenades" (no hyphen).

export const NADE_TYPES = [
  { value: 'smoke', label: 'Smokes', short: 'SMK', path: '' },
  { value: 'flashbang', label: 'Flashes', short: 'FLS', path: '/flashbangs' },
  { value: 'molotov', label: 'Mollies', short: 'MOL', path: '/molotovs' },
  { value: 'he', label: 'HE', short: 'HE', path: '/hegrenades' },
]

const byValue = Object.fromEntries(NADE_TYPES.map((n) => [n.value, n]))

export const nadeLabel = (value) => byValue[value]?.label ?? value

// de_overpass → https://csnades.gg/overpass ( + the type's path )
export function nadeUrl(map, nadeType) {
  const slug = String(map).replace(/^de_/, '')
  return `https://csnades.gg/${slug}${byValue[nadeType]?.path ?? ''}`
}
