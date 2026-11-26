import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, cleanParams } from '../lib/api'
import type { MovementType, Paginated, Product, Resource, StockMovement } from '../lib/types'
import { invalidateStock } from './inventory'

export interface ProductFilters {
  page?: number
  search?: string
  category_id?: string
  supplier_id?: string
  stock?: '' | 'attention' | 'out'
  sort?: 'name' | 'sku' | 'sell_price' | 'total_quantity'
  direction?: 'asc' | 'desc'
  per_page?: number
  /** '1' = list deleted products instead (admins and managers only) */
  deleted?: '' | '1'
}

/** What the product form sends. Money as strings, like the API returns it. */
export interface ProductInput {
  sku: string
  name: string
  description: string
  category_id: number | ''
  default_supplier_id: number | ''
  cost_price: string
  sell_price: string
  is_active: boolean
  reorder_point?: number
  reorder_qty?: number
}

/**
 * Cache keys. Everything starts with 'products', so invalidating ['products']
 * refreshes every list, detail page and movement history at once.
 */
export const productKeys = {
  all: ['products'] as const,
  list: (filters: ProductFilters) => ['products', 'list', filters] as const,
  detail: (id: number) => ['products', 'detail', id] as const,
  movements: (id: number, filters: object) => ['products', 'detail', id, 'movements', filters] as const,
}

export function useProducts(filters: ProductFilters) {
  return useQuery({
    queryKey: productKeys.list(filters),
    queryFn: () => api.get<Paginated<Product>>('/products', { params: cleanParams(filters) }).then((res) => res.data),
    // Keep showing the previous page while the next one loads (no flash of "Loading…")
    placeholderData: keepPreviousData,
  })
}

export function useProduct(id: number, options: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: productKeys.detail(id),
    queryFn: () => api.get<Resource<Product>>(`/products/${id}`).then((res) => res.data.data),
    enabled: options.enabled ?? true,
  })
}

export function useProductMovements(id: number, filters: { page: number; warehouse_id: string; type: '' | MovementType }) {
  return useQuery({
    queryKey: productKeys.movements(id, filters),
    queryFn: () =>
      api.get<Paginated<StockMovement>>(`/products/${id}/movements`, { params: cleanParams(filters) }).then((res) => res.data),
    placeholderData: keepPreviousData,
  })
}

/** Create when there's no id, update when there is. */
export function useSaveProduct() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: ProductInput }) => {
      const body = { ...data, default_supplier_id: data.default_supplier_id || null }
      const request = id ? api.put<Resource<Product>>(`/products/${id}`, body) : api.post<Resource<Product>>('/products', body)
      return request.then((res) => res.data.data)
    },
    onSuccess: () => void invalidateStock(queryClient),
  })
}

export function useDeleteProduct() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => api.delete(`/products/${id}`),
    // Refresh everything except the deleted product's own page, which would now be a 404
    onSuccess: (_response, id) =>
      void invalidateStock(queryClient, (query) => !(query.queryKey[1] === 'detail' && query.queryKey[2] === id)),
  })
}

/** Undo a delete. Refreshes everything, so the product shows up again in Products and Inventory. */
export function useRestoreProduct() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => api.post<Resource<Product>>(`/products/${id}/restore`).then((res) => res.data.data),
    onSuccess: () => void invalidateStock(queryClient),
  })
}
