import { STATUS_LABELS } from '../lib/stockStatus'
import type { StockStatus } from '../lib/types'

// Warnings are orange, never yellow, so they don't blend with the brand colour.
const styles: Record<StockStatus, string> = {
  ok: 'bg-success/10 text-success',
  low: 'bg-warning/10 text-warning',
  critical: 'bg-danger/10 text-danger',
  out: 'bg-danger text-canvas',
}

/** For products (or anything) switched off with is_active. Grey on purpose: paused, not a problem. */
export function InactiveBadge() {
  return <span className="inline-flex rounded-full border border-line-strong px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-muted">Inactive</span>
}

export function StatusBadge({ status }: { status: StockStatus }) {
  return <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${styles[status]}`}>{STATUS_LABELS[status]}</span>
}
