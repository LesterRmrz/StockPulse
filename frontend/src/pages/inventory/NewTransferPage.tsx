import { ArrowLeft, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useWarehouses } from '../../api/catalog'
import { useCreateTransfer } from '../../api/inventory'
import { useAuth } from '../../auth/auth-context'
import { Button } from '../../components/Button'
import { Field, FormError, Input, Select } from '../../components/Field'
import { PageHeader } from '../../components/PageHeader'
import { EmptyState } from '../../components/States'
import { errorMessage, fieldErrors } from '../../lib/api'
import { formatNumber } from '../../lib/format'
import { ProductPicker } from './ProductPicker'

interface Line {
  productId: number
  label: string
  available: number
  quantity: string
}

export function NewTransferPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const create = useCreateTransfer()
  const { data: warehouses } = useWarehouses()
  const [fromId, setFromId] = useState('')
  const [toId, setToId] = useState('')
  const [lines, setLines] = useState<Line[]>([])
  const [note, setNote] = useState('')

  if (!user?.can.transfer_stock) return <EmptyState title="Not allowed" />

  const active = warehouses?.filter((warehouse) => warehouse.is_active) ?? []
  const errors = fieldErrors(create.error)
  const overLimit = lines.some((line) => Number(line.quantity) > line.available)
  const ready = fromId && toId && fromId !== toId && lines.length > 0 && !overLimit && lines.every((line) => Number(line.quantity) >= 1)

  function changeFrom(id: string) {
    setFromId(id)
    setLines([]) // availability is per warehouse, so start the list over
  }

  function updateLine(index: number, quantity: string) {
    setLines(lines.map((line, i) => (i === index ? { ...line, quantity } : line)))
  }

  function submit(e: FormEvent) {
    e.preventDefault()
    create.mutate(
      {
        from_warehouse_id: Number(fromId),
        to_warehouse_id: Number(toId),
        items: lines.map((line) => ({ product_id: line.productId, quantity: Number(line.quantity) })),
        note,
      },
      { onSuccess: () => navigate('/inventory/transfers') },
    )
  }

  return (
    <>
      <Link to="/inventory/transfers" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink">
        <ArrowLeft size={16} aria-hidden="true" />
        Transfers
      </Link>
      <PageHeader title="New transfer" subtitle="Move stock from one warehouse to another. It's recorded as a pair of ledger movements." />

      <form onSubmit={submit} className="flex max-w-3xl flex-col gap-5 rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="From" error={errors.from_warehouse_id}>
            <Select required value={fromId} onChange={(e) => changeFrom(e.target.value)}>
              <option value="" disabled>
                Choose a warehouse
              </option>
              {active.map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                  {warehouse.code} · {warehouse.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="To" error={errors.to_warehouse_id ?? (fromId && fromId === toId ? 'Choose a different warehouse.' : undefined)}>
            <Select required value={toId} onChange={(e) => setToId(e.target.value)}>
              <option value="" disabled>
                Choose a warehouse
              </option>
              {active.map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                  {warehouse.code} · {warehouse.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>

        <div className="flex flex-col gap-3">
          <h2 className="text-sm font-medium">Products</h2>
          {lines.length > 0 && (
            <ul className="flex flex-col divide-y divide-line rounded-lg border border-line">
              {lines.map((line, index) => {
                const error = errors[`items.${index}.quantity`] ?? (Number(line.quantity) > line.available ? `Only ${line.available} available.` : undefined)
                return (
                  <li key={line.productId} className="flex flex-wrap items-center gap-3 px-3.5 py-3">
                    <div className="min-w-48 flex-1">
                      <div className="text-sm font-medium">{line.label}</div>
                      <div className="text-xs text-muted">{formatNumber(line.available)} available</div>
                    </div>
                    <div className="w-28">
                      <Input type="number" min={1} max={line.available} step={1} required aria-label={`Quantity of ${line.label}`} value={line.quantity} onChange={(e) => updateLine(index, e.target.value)} invalid={!!error} />
                    </div>
                    <Button size="sm" variant="ghost" onClick={() => setLines(lines.filter((_, i) => i !== index))} aria-label={`Remove ${line.label}`}>
                      <Trash2 size={16} aria-hidden="true" />
                    </Button>
                    {error && <p className="w-full text-xs text-danger">{error}</p>}
                  </li>
                )
              })}
            </ul>
          )}
          {fromId ? (
            <ProductPicker
              warehouseId={Number(fromId)}
              exclude={lines.map((line) => line.productId)}
              onPick={(level) =>
                setLines([...lines, { productId: level.product_id, label: `${level.product?.name} (${level.product?.sku})`, available: level.quantity, quantity: '1' }])
              }
            />
          ) : (
            <p className="text-sm text-muted">Choose the warehouse to send from first.</p>
          )}
        </div>

        <Field label="Note (optional)" error={errors.note}>
          <Input value={note} onChange={(e) => setNote(e.target.value)} maxLength={255} placeholder="e.g. Restock for the Cebu promo" />
        </Field>

        {/* e.g. "Not enough stock" if someone else moved it while this form was open */}
        <FormError message={create.isError ? errorMessage(create.error) : null} />

        <div className="flex justify-end gap-2 border-t border-line pt-5">
          <Button onClick={() => navigate('/inventory/transfers')}>Cancel</Button>
          <Button type="submit" variant="primary" disabled={!ready || create.isPending}>
            {create.isPending ? 'Moving stock…' : 'Complete transfer'}
          </Button>
        </div>
      </form>
    </>
  )
}
