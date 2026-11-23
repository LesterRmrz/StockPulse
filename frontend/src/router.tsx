import { createBrowserRouter } from 'react-router-dom'
import { GuestOnly, RequireAuth } from './auth/guards'
import { AppLayout } from './layouts/AppLayout'
import { NAV_ITEMS } from './navigation'
import { ComingSoonPage } from './pages/ComingSoonPage'
import { DashboardPage } from './pages/DashboardPage'
import { LoginPage } from './pages/LoginPage'
import { NotFoundPage } from './pages/NotFoundPage'

/**
 * Route tree:
 *
 *   /login             GuestOnly -> LoginPage
 *   /, /products, ...  RequireAuth -> AppLayout (sidebar) -> page
 *
 * Pages that aren't built yet render ComingSoonPage. Each milestone swaps its
 * entries here for real pages.
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
          ...NAV_ITEMS.filter((item) => item.path !== '/').map((item) => ({
            path: item.path,
            element: <ComingSoonPage item={item} />,
          })),
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
])
