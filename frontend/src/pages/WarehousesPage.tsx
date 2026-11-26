import { MapPin, Pencil, Plus } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { useSaveWarehouse, useWarehouses, type WarehouseInput } from '../api/catalog'
import { useAuth } from '../auth/auth-context'
import { BusyOverlay } from '../components/Busy'
import { Button } from '../components/Button'
import { Field, FormError, Input } from '../components/Field'
import { Modal } from '../components/Modal'
import { PageHeader } from '../components/PageHeader'
import { ErrorState, Loading } from '../components/States'
import { errorMessage, fieldErrors } from '../lib/api'
import { formatMoney, formatNumber } from '../lib/format'
import type { Warehouse } from '../lib/types'

export function WarehousesPage() {
  const { user } = useAuth()
  const { data: warehouses, isPending, isError, error, refetch, isFetching } = useWarehouses()
  const [editing, setEditing] = useState<Warehouse | null | undefined>(undefined)

  return (
    <>
      <PageHeader
        title="Warehouses"
        subtitle="Where stock is kept. Values are at cost price."
        actions={
          user?.can.manage_warehouses && (
            <Button variant="primary" onClick={() => setEditing(null)}>
              <Plus size={16} aria-hidden="true" />
              New warehouse
            </Button>
          )
        }
      />

      {isPending ? (
        <Loading />
      ) : isError ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : (
        // Refreshes after a stock change elsewhere or an edit here
        <BusyOverlay busy={isFetching}>
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {warehouses.map((warehouse) => (
              <article key={warehouse.id} className={`flex flex-col gap-4 rounded-2xl border border-line bg-surface p-5 ${warehouse.is_active ? '' : 'opacity-60'}`}>
                <header className="flex items-start justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="rounded-md bg-accent px-2 py-0.5 font-display text-sm font-semibold text-accent-ink">{warehouse.code}</span>
                      {!warehouse.is_active && <span className="text-xs text-muted">Inactive</span>}
                    </div>
                    <h2 className="mt-2 font-semibold">{warehouse.name}</h2>
                    <p className="mt-0.5 flex items-center gap-1 text-sm text-muted">
                      <MapPin size={14} aria-hidden="true" />
                      {warehouse.city}
                    </p>
                  </div>
                  {user?.can.manage_warehouses && (
                    <Button size="sm" variant="ghost" onClick={() => setEditing(warehouse)} aria-label={`Edit ${warehouse.code}`}>
                      <Pencil size={16} aria-hidden="true" />
                    </Button>
                  )}
                </header>
  
                <dl className="grid grid-cols-2 gap-3 text-sm">
                  <Figure label="Units on hand" value={formatNumber(warehouse.summary?.units ?? 0)} />
                  <Figure label="Stock value" value={formatMoney(warehouse.summary?.stock_value ?? 0)} />
                  <Figure label="SKUs in stock" value={formatNumber(warehouse.summary?.skus_in_stock ?? 0)} />
                  <Figure label="Need reordering" value={formatNumber(warehouse.summary?.needs_attention ?? 0)} warn={(warehouse.summary?.needs_attention ?? 0) > 0} />
                </dl>
  
                <Link to={`/inventory?warehouse_id=${warehouse.id}&status=attention`} className="mt-auto text-sm font-semibold text-accent hover:underline">
                  View items needing reorder →
                </Link>
              </article>
            ))}
          </div>
        </BusyOverlay>
      )}

      {editing !== undefined && <WarehouseDialog warehouse={editing} onClose={() => setEditing(undefined)} />}
    </>
  )
}

function Figure({ label, value, warn }: { label: string; value: string; warn?: boolean }) {
  return (
    <div className="rounded-lg bg-surface-2 px-3 py-2.5">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className={`font-display text-lg font-semibold ${warn ? 'text-warning' : ''}`}>{value}</dd>
    </div>
  )
}

function WarehouseDialog({ warehouse, onClose }: { warehouse: Warehouse | null; onClose: () => void }) {
  const save = useSaveWarehouse()
  const [form, setForm] = useState<WarehouseInput>({
    code: warehouse?.code ?? '',
    name: warehouse?.name ?? '',
    city: warehouse?.city ?? '',
    address: warehouse?.address ?? '',
    is_active: warehouse?.is_active ?? true,
  })
  const errors = fieldErrors(save.error)

  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate({ id: warehouse?.id, data: form }, { onSuccess: onClose })
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={warehouse ? `Edit ${warehouse.code}` : 'New warehouse'}
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button type="submit" form="warehouse-form" variant="primary" disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </Button>
        </>
      }
    >
      <form id="warehouse-form" onSubmit={submit} className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-[8rem_1fr]">
          <Field label="Code" error={errors.code}>
            <Input required maxLength={10} value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} invalid={!!errors.code} placeholder="ILO-01" className="uppercase" autoFocus />
          </Field>
          <Field label="Name" error={errors.name}>
            <Input required maxLength={100} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} invalid={!!errors.name} />
          </Field>
        </div>
        <Field label="City" error={errors.city}>
          <Input required maxLength={100} value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} invalid={!!errors.city} />
        </Field>
        <Field label="Address (optional)" error={errors.address}>
          <Input maxLength={255} value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
        </Field>
        {warehouse && (
          <label className="flex min-h-11 items-center gap-2.5 text-sm">
            <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="size-4.5 accent-accent" />
            Active (inactive warehouses keep their history but can't receive or send stock)
          </label>
        )}
        <FormError message={save.isError && Object.keys(errors).length === 0 ? errorMessage(save.error) : null} />
      </form>
    </Modal>
  )
}
