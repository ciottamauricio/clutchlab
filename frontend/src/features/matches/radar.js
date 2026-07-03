// World-coordinate → radar-image calibration for the overviews in public/radars/.
// Each map's radar PNG is 1024×1024; a world point maps to a normalized 0..1 position by
// pos_x/pos_y (the overview's upper-left world corner) and scale (world units per pixel).
// Values come from the CS2 map overview definitions (demoinfocs _assets metadata). This
// lives in the frontend on purpose: it's a render concern tied to these specific images
// (api/docs/domains/heatmap.md). Maps without an entry here have no radar and are skipped.
const CALIBRATION = {
  de_inferno: { posX: -2087, posY: 3870, scale: 4.9 },
  de_mirage: { posX: -3230, posY: 1713, scale: 5.0 },
  de_nuke: { posX: -3453, posY: 2887, scale: 7.0 },
  de_ancient: { posX: -2953, posY: 2164, scale: 5.0 },
  de_anubis: { posX: -2796, posY: 3328, scale: 5.22 },
  de_dust2: { posX: -2476, posY: 3239, scale: 4.4 },
  de_overpass: { posX: -4831, posY: 1781, scale: 5.2 },
  de_train: { posX: -2308, posY: 2078, scale: 4.082077 },
  de_vertigo: { posX: -3168, posY: 1762, scale: 4.0 },
}

const IMAGE_SIZE = 1024

export function hasRadar(map) {
  return Boolean(CALIBRATION[map])
}

export function radarUrl(map) {
  return `/radars/${map}.png`
}

// Returns { left, top } as 0..1 fractions of the image, or null if the map is unknown.
export function toRadar(map, x, y) {
  const c = CALIBRATION[map]
  if (!c) return null
  return {
    left: (x - c.posX) / c.scale / IMAGE_SIZE,
    top: (c.posY - y) / c.scale / IMAGE_SIZE,
  }
}
