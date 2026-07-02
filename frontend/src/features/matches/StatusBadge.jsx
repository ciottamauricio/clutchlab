import { t } from '../../lib/i18n'

export default function StatusBadge({ status }) {
  return <span className={`badge badge-${status}`}>{t(status)}</span>
}
