import { ArrowLeft } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useCategories, useSuppliers } from '../../api/catalog'
import { useProduct, useSaveProduct, type ProductInput } from '../../api/products'
import { useAuth } from '../../auth/auth-context'
import { Button } from '../../components/Button'
import { Field, FormError, Input, Select, Textarea } from '../../components/Field'
import { PageHeader } from '../../components/PageHeader'
import { EmptyState, ErrorState, Loading } from '../../components/States'
import { errorMessage, fieldErrors } from '../../lib/api'
import { formatMargin } from '../../lib/format'
import type { Product } from '../../lib/types'

const EMPTY: ProductInput = {
  sku: '',
  name: '',
  description: '',
  category_id: '',
  default_supplier_id: '',
  cost_price: '',
  sell_price: '',
  is_active: true,
  reorder_point: 10,
  reorder_qty: 30,
}

/** /products/new and /products/:id/edit share this page. */
export function ProductFormPage() {
  const { user } = useAuth()
  const params = useParams()
  const id = params.id ? Number(params.id) : undefined

  // `enabled: false` skips the request when creating (there's nothing to load)
  const product = useProduct(id ?? 0, { enabled: id !== undefined })

  if (!user?.can.manage_catalog) {
    return <EmptyState title="Not allowed">Only admins and managers can edit the catalog.</EmptyState>
  }
  if (id !== undefined && product.isPending) return <Loading />
  if (id !== undefined && product.isError) return <ErrorState error={product.error} />

  // `key` makes React start a fresh form (fresh useState) for each product
  return <ProductForm key={id ?? 'new'} product={id !== undefined ? product.data : undefined} />
}

function toInput(product: Product): ProductInput {
  return {
    sku: product.sku,
    name: product.name,
    description: product.description ?? '',
    category_id: product.category_id,
    default_supplier_id: product.default_supplier_id ?? '',
    cost_price: product.cost_price,
    sell_price: product.sell_price,
    is_active: product.is_active,
  }
}

function ProductForm({ product }: { product?: Product }) {
  const navigate = useNavigate()
  const save = useSaveProduct()
  const { data: categories } = useCategories()
  const { data: suppliers } = useSuppliers({ per_page: 100 })

  // One state object for the whole form, initialised once from the product
  const [form, setForm] = useState<ProductInput>(product ? toInput(product) : EMPTY)
  const errors = fieldErrors(save.error)
  const isEdit = product !== undefined

  /**
   * Returns an onChange handler for one field. `K extends keyof ProductInput`
   * means K must be one of the form's field names, and ProductInput[K] is that
   * field's type, so set('cost_price') can only receive a string.
   */
  function set<K extends keyof ProductInput>(key: K) {
    return (value: ProductInput[K]) => setForm((previous) => ({ ...previous, [key]: value }))
  }

  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate({ id: product?.id, data: form }, { onSuccess: (saved) => navigate(`/products/${saved.id}`) })
  }

  const backTo = isEdit ? `/products/${product.id}` : '/products'

  return (
    <>
      <Link to={backTo} className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink">
        <ArrowLeft size={16} aria-hidden="true" />
        {isEdit ? product.name : 'Products'}
      </Link>
      <PageHeader title={isEdit ? 'Edit product' : 'New product'} />

      <form onSubmit={submit} className="flex max-w-3xl flex-col gap-5 rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <div className="grid gap-4 sm:grid-cols-[12rem_1fr]">
          <Field label="SKU" error={errors.sku} hint="Letters, numbers and dashes">
            <Input required maxLength={32} value={form.sku} onChange={(e) => set('sku')(e.target.value)} invalid={!!errors.sku} placeholder="SP-ELC-0151" className="uppercase" />
          </Field>
          <Field label="Name" error={errors.name}>
            <Input required maxLength={150} value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={!!errors.name} />
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Category" error={errors.category_id}>
            <Select required value={form.category_id} onChange={(e) => set('category_id')(Number(e.target.value))} invalid={!!errors.category_id}>
              <option value="" disabled>
                Choose a category
              </option>
              {categories?.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.parent ? `${category.parent} › ` : ''}
                  {category.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Default supplier" error={errors.default_supplier_id}>
            <Select value={form.default_supplier_id} onChange={(e) => set('default_supplier_id')(e.target.value ? Number(e.target.value) : '')}>
              <option value="">None</option>
              {suppliers?.data.map((supplier) => (
                <option key={supplier.id} value={supplier.id}>
                  {supplier.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <Field label="Cost price (₱)" error={errors.cost_price}>
            <Input type="number" inputMode="decimal" required min={0} step="0.01" value={form.cost_price} onChange={(e) => set('cost_price')(e.target.value)} invalid={!!errors.cost_price} />
          </Field>
          <Field label="Sell price (₱)" error={errors.sell_price}>
            <Input type="number" inputMode="decimal" required min={0} step="0.01" value={form.sell_price} onChange={(e) => set('sell_price')(e.target.value)} invalid={!!errors.sell_price} />
          </Field>
          <div className="flex flex-col gap-1.5 text-sm font-medium">
            Margin
            <span className="flex h-11 items-center font-display text-lg">{form.cost_price && form.sell_price ? formatMargin(form.cost_price, form.sell_price) : '—'}</span>
          </div>
        </div>

        {!isEdit && (
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Reorder point" hint="Applied to every warehouse; adjust each one later" error={errors.reorder_point}>
              <Input type="number" min={0} step={1} value={form.reorder_point} onChange={(e) => set('reorder_point')(Number(e.target.value))} />
            </Field>
            <Field label="Reorder quantity" error={errors.reorder_qty}>
              <Input type="number" min={0} step={1} value={form.reorder_qty} onChange={(e) => set('reorder_qty')(Number(e.target.value))} />
            </Field>
          </div>
        )}

        <Field label="Description (optional)" error={errors.description}>
          <Textarea maxLength={2000} value={form.description} onChange={(e) => set('description')(e.target.value)} />
        </Field>

        <label className="flex min-h-11 items-center gap-2.5 text-sm">
          <input type="checkbox" checked={form.is_active} onChange={(e) => set('is_active')(e.target.checked)} className="size-4.5 accent-accent" />
          Active (inactive products stay in history but aren't sold)
        </label>

        <FormError message={save.isError && Object.keys(errors).length === 0 ? errorMessage(save.error) : null} />

        <div className="flex justify-end gap-2 border-t border-line pt-5">
          <Button onClick={() => navigate(backTo)}>Cancel</Button>
          <Button type="submit" variant="primary" disabled={save.isPending}>
            {save.isPending ? 'Saving…' : isEdit ? 'Save changes' : 'Create product'}
          </Button>
        </div>
      </form>
    </>
  )
}
