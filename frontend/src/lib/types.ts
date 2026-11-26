// Shapes of the JSON the Laravel API returns. Keep in sync with app/Http/Resources.

export type Role = 'admin' | 'manager' | 'staff'

/** What the signed-in user may do (from the policies, see UserResource). */
export interface Abilities {
  manage_catalog: boolean
  manage_warehouses: boolean
  adjust_stock: boolean
  transfer_stock: boolean
  edit_reorder_settings: boolean
}

export interface User {
  id: number
  name: string
  email: string
  role: Role
  role_label: string
  is_demo: boolean
  can: Abilities
}

/** Laravel API Resources wrap single objects in { data: ... } */
export interface Resource<T> {
  data: T
}

/** A Laravel paginator: one page of rows plus page info in `meta`. */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

export type StockStatus = 'ok' | 'low' | 'critical' | 'out'

export type MovementType = 'receipt' | 'sale' | 'return' | 'adjustment' | 'transfer_in' | 'transfer_out'

export interface Ref {
  id: number
  name: string
}

export interface WarehouseRef {
  id: number
  code: string
  name?: string
  is_active?: boolean
}

export interface StockLevel {
  id: number
  product_id: number
  warehouse_id: number
  product?: { id: number; sku: string; name: string; category: string | null; is_active: boolean }
  warehouse?: WarehouseRef
  quantity: number
  reorder_point: number
  reorder_qty: number
  status: StockStatus
  updated_at: string
}

export interface Product {
  id: number
  sku: string
  name: string
  description: string | null
  category_id: number
  default_supplier_id: number | null
  category?: Ref
  supplier?: Ref | null
  /** Money is a string like "39.00" so it never picks up float rounding errors */
  cost_price: string
  sell_price: string
  is_active: boolean
  total_quantity?: number
  /** How many warehouses are at or below the reorder point (list only) */
  attention_count?: number
  stock?: StockLevel[]
  /** Only set in the "Deleted" view of the list */
  deleted_at: string | null
  created_at: string
  updated_at: string
}

export interface StockMovement {
  id: number
  type: MovementType
  quantity_change: number
  quantity_after: number
  unit_cost: string | null
  warehouse?: WarehouseRef
  user?: string | null
  reference: { type: 'order' | 'purchase_order' | 'stock_transfer'; id: number; label: string | null } | null
  note: string | null
  created_at: string
}

export interface Category {
  id: number
  name: string
  slug: string
  parent_id: number | null
  parent?: string | null
  products_count?: number
}

export interface Supplier {
  id: number
  name: string
  contact_name: string | null
  email: string | null
  phone: string | null
  lead_time_days: number
  products_count?: number
  purchase_orders_count?: number
}

export interface Warehouse {
  id: number
  code: string
  name: string
  city: string
  address: string | null
  is_active: boolean
  summary?: {
    units: number
    skus_in_stock: number
    needs_attention: number
    stock_value: string
  }
}

export interface StockTransfer {
  id: number
  transfer_number: string
  status: 'pending' | 'completed' | 'cancelled'
  from?: WarehouseRef
  to?: WarehouseRef
  user?: string | null
  note: string | null
  items?: { product: { id: number; sku: string; name: string }; quantity: number }[]
  total_units?: number
  completed_at: string | null
  created_at: string
}
