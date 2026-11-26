import { createBrowserRouter } from 'react-router-dom'
import { GuestOnly, RequireAuth } from './auth/guards'
import { AppLayout } from './layouts/AppLayout'
import { NAV_ITEMS } from './navigation'
import { CategoriesPage } from './pages/CategoriesPage'
import { ComingSoonPage } from './pages/ComingSoonPage'
import { DashboardPage } from './pages/DashboardPage'
import { InventoryLayout } from './pages/inventory/InventoryLayout'
import { NewTransferPage } from './pages/inventory/NewTransferPage'
import { StockLevelsPage } from './pages/inventory/StockLevelsPage'
import { TransfersPage } from './pages/inventory/TransfersPage'
import { LoginPage } from './pages/LoginPage'
import { NotFoundPage } from './pages/NotFoundPage'
import { ProductDetailPage } from './pages/products/ProductDetailPage'
import { ProductFormPage } from './pages/products/ProductFormPage'
import { ProductsPage } from './pages/products/ProductsPage'
import { SuppliersPage } from './pages/SuppliersPage'
import { WarehousesPage } from './pages/WarehousesPage'

/** Menu items that have a real page. The rest still get a ComingSoonPage. */
const BUILT = ['/', '/products', '/categories', '/inventory', '/warehouses', '/suppliers']

/**
 * Route tree:
 *
 *   /login                         GuestOnly -> LoginPage
 *   everything else                RequireAuth -> AppLayout (sidebar) -> page
 *     /products, /products/new, /products/:id, /products/:id/edit
 *     /inventory (tabs: stock levels | transfers), /inventory/transfers/new
 *     /categories, /suppliers, /warehouses
 */
export const router = createBrowserRouter([
  {
    element: <GuestOnly />,
    children: [{ path: '/login', element: <LoginPage /> }],
  },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <DashboardPage /> },

          { path: '/products', element: <ProductsPage /> },
          { path: '/products/new', element: <ProductFormPage /> },
          // :id is a URL parameter, read in the page with useParams()
          { path: '/products/:id', element: <ProductDetailPage /> },
          { path: '/products/:id/edit', element: <ProductFormPage /> },

          {
            path: '/inventory',
            element: <InventoryLayout />,
            children: [
              { index: true, element: <StockLevelsPage /> },
              { path: 'transfers', element: <TransfersPage /> },
            ],
          },
          { path: '/inventory/transfers/new', element: <NewTransferPage /> },

          { path: '/categories', element: <CategoriesPage /> },
          { path: '/suppliers', element: <SuppliersPage /> },
          { path: '/warehouses', element: <WarehousesPage /> },

          ...NAV_ITEMS.filter((item) => !BUILT.includes(item.path)).map((item) => ({
            path: item.path,
            element: <ComingSoonPage item={item} />,
          })),
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
])
