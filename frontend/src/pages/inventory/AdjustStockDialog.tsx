import { useState, type FormEvent } from 'react'
import { useAdjustStock } from '../../api/inventory'
import { Button } from '../../components/Button'
import { Field, FormError, Input, Select } from '../../components/Field'
import { Modal } from '../../components/Modal'
import { errorMessage, fieldErrors } from '../../lib/api'
import { formatNumber } from '../../lib/format'

/** The minimum the dialog needs to know about the stock row it adjusts. */
export interface AdjustTarget {
  productId: number
  productLabel: string
  warehouseId: number
  warehouseCode: string
  quantity: number
}

type Mode = 'add' | 'remove' | 'count'

const REASONS = ['Recount', 'Damaged', 'Lost or stolen', 'Found', 'Returned to supplier', 'Other']

/**
 * Rendered only while open (the parent does `{target && <AdjustStockDialog …/>}`),
 * so every time it opens, its useState starts fresh: no stale form values.
 */
export function AdjustStockDialog({ target, onClose }: { target: AdjustTarget; onClose: () => void }) {
  const adjust = useAdjustStock()
  const [mode, setMode] = useState<Mode>('add')
  const [amount, setAmount] = useState('')
  const [reason, setReason] = useState(REASONS[0])
  const [note, setNote] = useState('')

  const value = Number(amount)
  const valid = amount !== '' && Number.isInteger(value) && value >= (mode === 'count' ? 0 : 1)
  // Preview what the quantity will become (the server recalculates it inside a lock)
  const after = !valid ? null : mode === 'add' ? target.quantity + value : mode === 'remove' ? target.quantity - value : value
  const errors = fieldErrors(adjust.error)

  function submit(e: FormEvent) {
    e.preventDefault()
    if (!valid) return

    adjust.mutate(
      {
        product_id: target.productId,
        warehouse_id: target.warehouseId,
        mode: mode === 'count' ? 'count' : 'change',
        quantity: mode === 'remove' ? -value : value,
        reason: note.trim() ? `${reason}: ${note.trim()}` : reason,
      },
      { onSuccess: onClose },
    )
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="Adjust stock"
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button type="submit" form="adjust-form" variant="primary" disabled={!valid || adjust.isPending || (after !== null && after < 0)}>
            {adjust.isPending ? 'Saving…' : 'Save adjustment'}
          </Button>
        </>
      }
    >
      <form id="adjust-form" onSubmit={submit} className="flex flex-col gap-4">
        <div className="rounded-lg bg-surface-2 px-3.5 py-3 text-sm">
          <div className="font-medium">{target.productLabel}</div>
          <div className="text-muted">
            {target.warehouseCode} · {formatNumber(target.quantity)} on hand
          </div>
        </div>

        {/* A segmented control: three radio buttons styled as tabs */}
        <fieldset className="grid grid-cols-3 gap-1 rounded-lg border border-line-strong p-1">
          <legend className="sr-only">Type of adjustment</legend>
          {(
            [
              ['add', 'Add'],
              ['remove', 'Remove'],
              ['count', 'Set to count'],
            ] as const
          ).map(([value, label]) => (
            <label
              key={value}
              className={`grid h-9 cursor-pointer place-items-center rounded-md text-sm font-medium ${mode === value ? 'bg-accent text-accent-ink' : 'text-muted hover:text-ink'}`}
            >
              <input type="radio" name="mode" value={value} checked={mode === value} onChange={() => setMode(value)} className="sr-only" />
              {label}
            </label>
          ))}
        </fieldset>

        <Field
          label={mode === 'count' ? 'Counted quantity' : 'Quantity'}
          error={errors.quantity}
          hint={after !== null && (after < 0 ? `Only ${target.quantity} on hand.` : `${formatNumber(target.quantity)} → ${formatNumber(after)}`)}
        >
          <Input type="number" inputMode="numeric" min={mode === 'count' ? 0 : 1} step={1} required value={amount} onChange={(e) => setAmount(e.target.value)} invalid={!!errors.quantity} autoFocus />
        </Field>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Reason">
            <Select value={reason} onChange={(e) => setReason(e.target.value)}>
              {REASONS.map((r) => (
                <option key={r}>{r}</option>
              ))}
            </Select>
          </Field>
          <Field label="Note (optional)" error={errors.reason}>
            <Input value={note} onChange={(e) => setNote(e.target.value)} maxLength={200} placeholder="e.g. Box crushed in delivery" />
          </Field>
        </div>

        {/* Errors that aren't about one of the fields above */}
        <FormError message={adjust.isError && !errors.quantity && !errors.reason ? errorMessage(adjust.error) : null} />
      </form>
    </Modal>
  )
}
