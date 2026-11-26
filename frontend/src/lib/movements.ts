import type { MovementType } from './types'

/** Labels for the stock ledger's movement types (App\Enums\MovementType). */
export const MOVEMENT_LABELS: Record<MovementType, string> = {
  receipt: 'Received',
  sale: 'Sale',
  return: 'Return',
  adjustment: 'Adjustment',
  transfer_out: 'Transfer out',
  transfer_in: 'Transfer in',
}
