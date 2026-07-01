import { t } from '../i18n/messages'

export default function StatusBadge({ status }) {
  return <span className={`badge badge-${status}`}>{t(status)}</span>
}
