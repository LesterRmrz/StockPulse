import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from './auth-context'

function FullScreenLoader() {
  return (
    <div className="grid min-h-dvh place-items-center" role="status" aria-live="polite">
      <span className="text-sm text-muted">Loading StockPulse…</span>
    </div>
  )
}

/** Wraps pages that need a logged-in user. Guests are sent to /login. */
export function RequireAuth() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') return <FullScreenLoader />
  if (status === 'guest') {
    // Remember where they were going, so login can send them back there.
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }
  return <Outlet />
}

/**
 * Wraps the login page. Logged-in users go straight to the app: either the
 * page they originally asked for (saved by RequireAuth) or the dashboard.
 * This is also what moves the user on right after a successful login.
 */
export function GuestOnly() {
  const { status } = useAuth()
  const location = useLocation()
  const from = (location.state as { from?: string } | null)?.from ?? '/'

  if (status === 'loading') return <FullScreenLoader />
  if (status === 'authenticated') return <Navigate to={from} replace />
  return <Outlet />
}
