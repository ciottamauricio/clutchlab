// Canonical CS2 weapon registry for the search selects. Each `value` is the exact
// string the worker stores for a kill: demoinfocs `Equipment.String()` lowercased with
// every non-alphanumeric character stripped (worker/internal/parser/parser.go,
// weaponName). Labels are the demoinfocs display names. Keep this in sync with the
// parser's set — if the worker ever emits a weapon not listed here, that value just
// won't be selectable. Grenades that can't produce kills (flash/smoke/decoy) are omitted;
// `world` is the environment (falls, bomb, suicide), kept so those kills are filterable.
export const WEAPON_GROUPS = [
  {
    group: 'Rifles',
    weapons: [
      { value: 'ak47', label: 'AK-47' },
      { value: 'm4a4', label: 'M4A4' },
      { value: 'm4a1', label: 'M4A1-S' },
      { value: 'galilar', label: 'Galil AR' },
      { value: 'famas', label: 'FAMAS' },
      { value: 'sg553', label: 'SG 553' },
      { value: 'aug', label: 'AUG' },
    ],
  },
  {
    group: 'Snipers',
    weapons: [
      { value: 'awp', label: 'AWP' },
      { value: 'ssg08', label: 'SSG 08' },
      { value: 'scar20', label: 'SCAR-20' },
      { value: 'g3sg1', label: 'G3SG1' },
    ],
  },
  {
    group: 'SMGs',
    weapons: [
      { value: 'mp9', label: 'MP9' },
      { value: 'mac10', label: 'MAC-10' },
      { value: 'mp7', label: 'MP7' },
      { value: 'mp5sd', label: 'MP5-SD' },
      { value: 'ump45', label: 'UMP-45' },
      { value: 'p90', label: 'P90' },
      { value: 'ppbizon', label: 'PP-Bizon' },
    ],
  },
  {
    group: 'Pistols',
    weapons: [
      { value: 'glock18', label: 'Glock-18' },
      { value: 'usps', label: 'USP-S' },
      { value: 'p2000', label: 'P2000' },
      { value: 'p250', label: 'P250' },
      { value: 'fiveseven', label: 'Five-SeveN' },
      { value: 'tec9', label: 'Tec-9' },
      { value: 'cz75auto', label: 'CZ75-Auto' },
      { value: 'dualberettas', label: 'Dual Berettas' },
      { value: 'deserteagle', label: 'Desert Eagle' },
      { value: 'r8revolver', label: 'R8 Revolver' },
    ],
  },
  {
    group: 'Heavy',
    weapons: [
      { value: 'nova', label: 'Nova' },
      { value: 'xm1014', label: 'XM1014' },
      { value: 'mag7', label: 'MAG-7' },
      { value: 'sawedoff', label: 'Sawed-Off' },
      { value: 'm249', label: 'M249' },
      { value: 'negev', label: 'Negev' },
    ],
  },
  {
    group: 'Other',
    weapons: [
      { value: 'knife', label: 'Knife' },
      { value: 'hegrenade', label: 'HE Grenade' },
      { value: 'molotov', label: 'Molotov' },
      { value: 'incendiarygrenade', label: 'Incendiary' },
      { value: 'zeusx27', label: 'Zeus x27' },
      { value: 'c4', label: 'C4' },
      { value: 'world', label: 'Environment' },
    ],
  },
]

export const WEAPONS = WEAPON_GROUPS.flatMap((g) => g.weapons)
