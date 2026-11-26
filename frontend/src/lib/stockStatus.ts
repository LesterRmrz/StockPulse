import type { StockStatus } from './types'

/** Labels for App\Enums\StockStatus, in severity order. */
export const STATUS_LABELS: Record<StockStatus, string> = {
  ok: 'In stock',
  low: 'Low',
  critical: 'Critical',
  out: 'Out of stock',
}
