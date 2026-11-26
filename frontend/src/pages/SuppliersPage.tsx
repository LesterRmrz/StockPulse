import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { useDeleteSupplier, useSaveSupplier, useSuppliers, type SupplierInput } from '../api/catalog'
import { useAuth } from '../auth/auth-context'
import { Button } from '../components/Button'
import { ConfirmDialog } from '../components/ConfirmDialog'
import { Field, FormError, Input } from '../components/Field'
import { Modal } from '../components/Modal'
import { PageHeader } from '../components/PageHeader'
import { Pagination } from '../components/Pagination'
import { SearchInput } from '../components/SearchInput'
import { EmptyState, ErrorState, Loading } from '../components/States'
import { table, TableCard, td, th, tr } from '../components/Table'
import { errorMessage, fieldErrors } from '../lib/api'
import type { Supplier } from '../lib/types'
import { useDebouncedValue } from '../lib/useDebouncedValue'
import { useUrlFilters } from '../lib/useUrlFilters'

export function SuppliersPage() {
  const { user } = useAuth()
  const remove = useDeleteSupplier()
  const [filters, setFilters] = useUrlFilters({ search: '', page: '1' })
  const search = useDebouncedValue(filters.search)
  const { data, isPending, isError, error, refetch, isFetching } = useSuppliers({ search, page: Number(filters.page) })
  const [editing, setEditing] = useState<Supplier | null | undefined>(undefined)
  const [deleting, setDeleting] = useState<Supplier | null>(null)
  const canManage = user?.can.manage_catalog

  return (
    <>
      <PageHeader
        title="Suppliers"
        subtitle="Companies we buy from. Lead time drives how early stock is reordered."
        actions={
          canManage && (
            <Button variant="primary" onClick={() => setEditing(null)}>
              <Plus size={16} aria-hidden="true" />
              New supplier
            </Button>
          )
        }
      />

      <TableCard busy={isFetching && !isPending} toolbar={<SearchInput value={filters.search} onChange={(value) => setFilters({ search: value })} placeholder="Search name, contact or email…" />}>
        {isPending ? (
          <Loading />
        ) : isError ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : data.data.length === 0 ? (
          <EmptyState title="No suppliers found" />
        ) : (
          <table className={table}>
            <thead>
              <tr>
                <th className={th}>Supplier</th>
                <th className={th}>Contact</th>
                <th className={`${th} text-right`}>Lead time</th>
                <th className={`${th} text-right`}>Products</th>
                <th className={`${th} text-right`}>POs</th>
                <th className={th}>
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((supplier) => (
                <tr key={supplier.id} className={tr}>
                  <td className={`${td} font-medium`}>{supplier.name}</td>
                  <td className={td}>
                    <div>{supplier.contact_name ?? '—'}</div>
                    <div className="text-xs text-muted">{[supplier.email, supplier.phone].filter(Boolean).join(' · ')}</div>
                  </td>
                  <td className={`${td} text-right whitespace-nowrap`}>{supplier.lead_time_days} days</td>
                  <td className={`${td} text-right`}>
                    <Link to={`/products?supplier_id=${supplier.id}`} className="hover:text-accent">
                      {supplier.products_count}
                    </Link>
                  </td>
                  <td className={`${td} text-right text-muted`}>{supplier.purchase_orders_count}</td>
                  <td className={`${td} text-right whitespace-nowrap`}>
                    {canManage && (
                      <>
                        <Button size="sm" variant="ghost" onClick={() => setEditing(supplier)} aria-label={`Edit ${supplier.name}`}>
                          <Pencil size={16} aria-hidden="true" />
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setDeleting(supplier)} aria-label={`Delete ${supplier.name}`}>
                          <Trash2 size={16} aria-hidden="true" />
                        </Button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </TableCard>
      {data && <Pagination meta={data.meta} onPage={(page) => setFilters({ page: String(page) })} />}

      {editing !== undefined && <SupplierDialog supplier={editing} onClose={() => setEditing(undefined)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete supplier?"
        message={`${deleting?.name} will be deleted and its products will have no default supplier. Suppliers with purchase orders can't be deleted.`}
        onConfirm={() => remove.mutateAsync(deleting!.id)}
      />
    </>
  )
}

function SupplierDialog({ supplier, onClose }: { supplier: Supplier | null; onClose: () => void }) {
  const save = useSaveSupplier()
  const [form, setForm] = useState<SupplierInput>({
    name: supplier?.name ?? '',
    contact_name: supplier?.contact_name ?? '',
    email: supplier?.email ?? '',
    phone: supplier?.phone ?? '',
    lead_time_days: supplier?.lead_time_days ?? 7,
  })
  const errors = fieldErrors(save.error)

  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate({ id: supplier?.id, data: form }, { onSuccess: onClose })
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={supplier ? 'Edit supplier' : 'New supplier'}
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button type="submit" form="supplier-form" variant="primary" disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </Button>
        </>
      }
    >
      <form id="supplier-form" onSubmit={submit} className="flex flex-col gap-4">
        <Field label="Company name" error={errors.name}>
          <Input required maxLength={150} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} invalid={!!errors.name} autoFocus />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Contact person" error={errors.contact_name}>
            <Input maxLength={100} value={form.contact_name} onChange={(e) => setForm({ ...form, contact_name: e.target.value })} />
          </Field>
          <Field label="Lead time (days)" error={errors.lead_time_days} hint="Order to delivery">
            <Input type="number" required min={1} max={365} value={form.lead_time_days} onChange={(e) => setForm({ ...form, lead_time_days: Number(e.target.value) })} invalid={!!errors.lead_time_days} />
          </Field>
          <Field label="Email" error={errors.email}>
            <Input type="email" maxLength={150} value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} invalid={!!errors.email} />
          </Field>
          <Field label="Phone" error={errors.phone}>
            <Input type="tel" maxLength={40} value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          </Field>
        </div>
        <FormError message={save.isError && Object.keys(errors).length === 0 ? errorMessage(save.error) : null} />
      </form>
    </Modal>
  )
}
