import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, cleanParams } from '../lib/api'
import type { Category, Paginated, Resource, Supplier, Warehouse } from '../lib/types'

// ---- Categories -------------------------------------------------------------

export function useCategories() {
  return useQuery({
    queryKey: ['categories'],
    queryFn: () => api.get<{ data: Category[] }>('/categories').then((res) => res.data.data),
    staleTime: 5 * 60_000, // rarely change: reuse for 5 minutes (they fill dropdowns everywhere)
  })
}

export function useSaveCategory() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: { name: string; parent_id: number | null } }) =>
      (id ? api.put<Resource<Category>>(`/categories/${id}`, data) : api.post<Resource<Category>>('/categories', data)).then(
        (res) => res.data.data,
      ),
    // Products show their category name, so refresh those too
    onSuccess: () => void Promise.all([queryClient.invalidateQueries({ queryKey: ['categories'] }), queryClient.invalidateQueries({ queryKey: ['products'] })]),
  })
}

export function useDeleteCategory() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => api.delete(`/categories/${id}`),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['categories'] }),
  })
}

// ---- Suppliers --------------------------------------------------------------

export interface SupplierInput {
  name: string
  contact_name: string
  email: string
  phone: string
  lead_time_days: number
}

export function useSuppliers(filters: { page?: number; search?: string; per_page?: number }) {
  return useQuery({
    queryKey: ['suppliers', filters],
    queryFn: () => api.get<Paginated<Supplier>>('/suppliers', { params: cleanParams(filters) }).then((res) => res.data),
    placeholderData: keepPreviousData,
  })
}

export function useSaveSupplier() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: SupplierInput }) =>
      (id ? api.put<Resource<Supplier>>(`/suppliers/${id}`, data) : api.post<Resource<Supplier>>('/suppliers', data)).then(
        (res) => res.data.data,
      ),
    onSuccess: () => void Promise.all([queryClient.invalidateQueries({ queryKey: ['suppliers'] }), queryClient.invalidateQueries({ queryKey: ['products'] })]),
  })
}

export function useDeleteSupplier() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => api.delete(`/suppliers/${id}`),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['suppliers'] }),
  })
}

// ---- Warehouses -------------------------------------------------------------

export interface WarehouseInput {
  code: string
  name: string
  city: string
  address: string
  is_active: boolean
}

export function useWarehouses() {
  return useQuery({
    queryKey: ['warehouses'],
    queryFn: () => api.get<{ data: Warehouse[] }>('/warehouses').then((res) => res.data.data),
  })
}

export function useSaveWarehouse() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: WarehouseInput }) =>
      (id ? api.put<Resource<Warehouse>>(`/warehouses/${id}`, data) : api.post<Resource<Warehouse>>('/warehouses', data)).then(
        (res) => res.data.data,
      ),
    // A new warehouse adds stock rows for every product
    onSuccess: () =>
      void Promise.all(['warehouses', 'stock-levels', 'products'].map((key) => queryClient.invalidateQueries({ queryKey: [key] }))),
  })
}
