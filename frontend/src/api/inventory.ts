import { keepPreviousData, useMutation, useQuery, useQueryClient, type Query, type QueryClient } from '@tanstack/react-query'
import { api, cleanParams } from '../lib/api'
import type { Paginated, Resource, StockLevel, StockStatus, StockTransfer } from '../lib/types'

/**
 * A stock change touches many screens: product lists and totals, the
 * inventory table, warehouse totals and the transfer list. Marking all of
 * them stale makes each one refetch the next time it's on screen.
 *
 * Mutations call it as `onSuccess: () => void invalidateStock(...)`. The
 * `void` means "don't wait for the refetch": the dialog closes as soon as the
 * save succeeds and the numbers refresh a moment later. (Returning the
 * Promise instead would keep the mutation "pending" until every refetch finished.)
 */
export function invalidateStock(queryClient: QueryClient, predicate?: (query: Query) => boolean) {
  return Promise.all(
    ['products', 'stock-levels', 'warehouses', 'stock-transfers'].map((key) =>
      queryClient.invalidateQueries({ queryKey: [key], predicate }),
    ),
  )
}

export interface StockLevelFilters {
  page?: number
  search?: string
  warehouse_id?: string
  category_id?: string
  status?: '' | StockStatus | 'attention'
  sort?: 'urgency' | 'name' | 'quantity'
  per_page?: number
}

export function useStockLevels(filters: StockLevelFilters) {
  return useQuery({
    queryKey: ['stock-levels', filters],
    queryFn: () => api.get<Paginated<StockLevel>>('/stock-levels', { params: cleanParams(filters) }).then((res) => res.data),
    placeholderData: keepPreviousData,
  })
}

export interface AdjustmentInput {
  product_id: number
  warehouse_id: number
  mode: 'change' | 'count'
  quantity: number
  reason: string
}

export function useAdjustStock() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: AdjustmentInput) =>
      api.post<Resource<StockLevel>>('/stock-adjustments', data).then((res) => res.data.data),
    onSuccess: () => void invalidateStock(queryClient),
  })
}

export function useUpdateReorderSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...data }: { id: number; reorder_point: number; reorder_qty: number }) =>
      api.patch<Resource<StockLevel>>(`/stock-levels/${id}`, data).then((res) => res.data.data),
    onSuccess: () => void invalidateStock(queryClient),
  })
}

export function useTransfers(filters: { page: number; warehouse_id: string }) {
  return useQuery({
    queryKey: ['stock-transfers', filters],
    queryFn: () =>
      api.get<Paginated<StockTransfer>>('/stock-transfers', { params: cleanParams(filters) }).then((res) => res.data),
    placeholderData: keepPreviousData,
  })
}

export interface TransferInput {
  from_warehouse_id: number
  to_warehouse_id: number
  items: { product_id: number; quantity: number }[]
  note: string
}

export function useCreateTransfer() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: TransferInput) =>
      api.post<Resource<StockTransfer>>('/stock-transfers', data).then((res) => res.data.data),
    onSuccess: () => void invalidateStock(queryClient),
  })
}
