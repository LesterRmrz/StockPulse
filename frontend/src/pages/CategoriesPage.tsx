import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { useCategories, useDeleteCategory, useSaveCategory } from '../api/catalog'
import { useAuth } from '../auth/auth-context'
import { Button } from '../components/Button'
import { ConfirmDialog } from '../components/ConfirmDialog'
import { Field, FormError, Input, Select } from '../components/Field'
import { Modal } from '../components/Modal'
import { PageHeader } from '../components/PageHeader'
import { EmptyState, ErrorState, Loading } from '../components/States'
import { table, TableCard, td, th, tr } from '../components/Table'
import { errorMessage, fieldErrors } from '../lib/api'
import type { Category } from '../lib/types'

export function CategoriesPage() {
  const { user } = useAuth()
  const { data: categories, isPending, isError, error, refetch, isFetching } = useCategories()
  const remove = useDeleteCategory()
  // undefined = dialog closed, null = creating, Category = editing that one
  const [editing, setEditing] = useState<Category | null | undefined>(undefined)
  const [deleting, setDeleting] = useState<Category | null>(null)
  const canManage = user?.can.manage_catalog

  // Show each top-level category followed by its subcategories
  const ordered = (categories ?? [])
    .filter((category) => category.parent_id === null)
    .flatMap((parent) => [parent, ...(categories ?? []).filter((child) => child.parent_id === parent.id)])

  return (
    <>
      <PageHeader
        title="Categories"
        subtitle="Group products. Categories can have one level of subcategories."
        actions={
          canManage && (
            <Button variant="primary" onClick={() => setEditing(null)}>
              <Plus size={16} aria-hidden="true" />
              New category
            </Button>
          )
        }
      />

      <TableCard busy={isFetching && !isPending}>
        {isPending ? (
          <Loading />
        ) : isError ? (
          <ErrorState error={error} onRetry={() => void refetch()} />
        ) : ordered.length === 0 ? (
          <EmptyState title="No categories yet" />
        ) : (
          <table className={table}>
            <thead>
              <tr>
                <th className={th}>Name</th>
                <th className={`${th} text-right`}>Products</th>
                <th className={th}>
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {ordered.map((category) => (
                <tr key={category.id} className={tr}>
                  <td className={td}>
                    <span className={category.parent_id ? 'pl-6 text-muted before:mr-2 before:content-["└"]' : 'font-medium'}>{category.name}</span>
                  </td>
                  <td className={`${td} text-right`}>
                    <Link to={`/products?category_id=${category.id}`} className="hover:text-accent">
                      {category.products_count}
                    </Link>
                  </td>
                  <td className={`${td} text-right whitespace-nowrap`}>
                    {canManage && (
                      <>
                        <Button size="sm" variant="ghost" onClick={() => setEditing(category)} aria-label={`Edit ${category.name}`}>
                          <Pencil size={16} aria-hidden="true" />
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setDeleting(category)} aria-label={`Delete ${category.name}`}>
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

      {editing !== undefined && <CategoryDialog category={editing} parents={(categories ?? []).filter((c) => c.parent_id === null)} onClose={() => setEditing(undefined)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete category?"
        message={`“${deleting?.name}” will be deleted. Its subcategories become top-level. Categories that still have products can't be deleted.`}
        onConfirm={() => remove.mutateAsync(deleting!.id)}
      />
    </>
  )
}

function CategoryDialog({ category, parents, onClose }: { category: Category | null; parents: Category[]; onClose: () => void }) {
  const save = useSaveCategory()
  const [name, setName] = useState(category?.name ?? '')
  const [parentId, setParentId] = useState(category?.parent_id ? String(category.parent_id) : '')
  const errors = fieldErrors(save.error)

  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate({ id: category?.id, data: { name, parent_id: parentId ? Number(parentId) : null } }, { onSuccess: onClose })
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={category ? 'Edit category' : 'New category'}
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button type="submit" form="category-form" variant="primary" disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save'}
          </Button>
        </>
      }
    >
      <form id="category-form" onSubmit={submit} className="flex flex-col gap-4">
        <Field label="Name" error={errors.name}>
          <Input required maxLength={100} value={name} onChange={(e) => setName(e.target.value)} invalid={!!errors.name} autoFocus />
        </Field>
        <Field label="Parent" error={errors.parent_id}>
          <Select value={parentId} onChange={(e) => setParentId(e.target.value)} invalid={!!errors.parent_id}>
            <option value="">None (top-level)</option>
            {parents
              .filter((parent) => parent.id !== category?.id)
              .map((parent) => (
                <option key={parent.id} value={parent.id}>
                  {parent.name}
                </option>
              ))}
          </Select>
        </Field>
        <FormError message={save.isError && Object.keys(errors).length === 0 ? errorMessage(save.error) : null} />
      </form>
    </Modal>
  )
}
