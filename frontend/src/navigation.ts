import {
  Activity,
  BarChart3,
  Boxes,
  ClipboardList,
  LayoutDashboard,
  Package,
  ShoppingCart,
  Tags,
  Truck,
  Warehouse,
  type LucideIcon,
} from 'lucide-react'

export interface NavItem {
  label: string
  path: string
  icon: LucideIcon
  /** Milestone that builds this page (shown on its placeholder until then) */
  milestone: string
}

/** The sidebar menu, and the list of routes the router creates. */
export const NAV_ITEMS: NavItem[] = [
  { label: 'Dashboard', path: '/', icon: LayoutDashboard, milestone: 'M5' },
  { label: 'Products', path: '/products', icon: Package, milestone: 'M2' },
  { label: 'Categories', path: '/categories', icon: Tags, milestone: 'M2' },
  { label: 'Inventory', path: '/inventory', icon: Boxes, milestone: 'M2' },
  { label: 'Warehouses', path: '/warehouses', icon: Warehouse, milestone: 'M2' },
  { label: 'Purchase orders', path: '/purchase-orders', icon: ClipboardList, milestone: 'M3' },
  { label: 'Sales orders', path: '/orders', icon: ShoppingCart, milestone: 'M3' },
  { label: 'Suppliers', path: '/suppliers', icon: Truck, milestone: 'M2' },
  { label: 'Analytics', path: '/analytics', icon: BarChart3, milestone: 'M5' },
  { label: 'Simulator', path: '/simulator', icon: Activity, milestone: 'M4' },
]
