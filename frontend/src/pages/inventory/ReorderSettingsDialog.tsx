import { useState, type FormEvent } from 'react'
import { useUpdateReorderSettings } from '../../api/inventory'
import { Button } from '../../components/Button'
import { Field, FormError, Input } from '../../components/Field'
import { Modal } from '../../components/Modal'
import { errorMessage, fieldErrors } from '../../lib/api'
import type { StockLevel } from '../../lib/types'

/**
 * Reorder point: when stock falls to this number, it's time to reorder.
 * Reorder quantity: how many to order when that happens.
 */
export function ReorderSettingsDialog({ level, label, onClose }: { level: StockLevel; label: string; onClose: () => void }) {
  const update = useUpdateReorderSettings()
  const [point, setPoint] = useState(String(level.reorder_point))
  const [qty, setQty] = useState(String(level.reorder_qty))
  const errors = fieldErrors(update.error)

  function submit(e: FormEvent) {
    e.preventDefault()
    update.mutate({ id: level.id, reorder_point: Number(point), reorder_qty: Number(qty) }, { onSuccess: onClose })
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="Reorder settings"
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button type="submit" form="reorder-form" variant="primary" disabled={update.isPending}>
            {update.isPending ? 'Saving…' : 'Save'}
          </Button>
        </>
      }
    >
      <form id="reorder-form" onSubmit={submit} className="flex flex-col gap-4">
        <p className="text-sm text-muted">{label}</p>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Reorder point" error={errors.reorder_point} hint="Flag as low at or below this">
            <Input type="number" min={0} step={1} required value={point} onChange={(e) => setPoint(e.target.value)} invalid={!!errors.reorder_point} />
          </Field>
          <Field label="Reorder quantity" error={errors.reorder_qty} hint="How many to order">
            <Input type="number" min={0} step={1} required value={qty} onChange={(e) => setQty(e.target.value)} invalid={!!errors.reorder_qty} />
          </Field>
        </div>
        <FormError message={update.isError && Object.keys(errors).length === 0 ? errorMessage(update.error) : null} />
      </form>
    </Modal>
  )
}
