import { useState } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { LogOut, Menu, X } from 'lucide-react'
import { useAuth } from '../auth/auth-context'
import { Spinner } from '../components/Busy'
import { Logo } from '../components/Logo'
import { NAV_ITEMS } from '../navigation'

/**
 * The shell around every logged-in page: black sidebar + content area.
 * On small screens the sidebar becomes a slide-in drawer behind a menu button.
 */
export function AppLayout() {
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <div className="min-h-dvh lg:flex">
      {/* Mobile top bar */}
      <header className="sticky top-0 z-30 flex items-center justify-between border-b border-line bg-canvas px-4 py-3 lg:hidden">
        <Logo size={28} />
        <button
          type="button"
          onClick={() => setMenuOpen(true)}
          className="grid size-11 place-items-center rounded-lg border border-line-strong"
          aria-label="Open menu"
          aria-expanded={menuOpen}
          aria-controls="sidebar"
        >
          <Menu size={20} aria-hidden="true" />
        </button>
      </header>

      {menuOpen && (
        <div className="fixed inset-0 z-40 bg-black/60 lg:hidden" onClick={() => setMenuOpen(false)} aria-hidden="true" />
      )}

      <aside
        id="sidebar"
        className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col gap-7 border-r border-line bg-canvas px-4 py-6 transition-transform lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0 ${
          menuOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center justify-between px-2">
          <Logo />
          <button
            type="button"
            onClick={() => setMenuOpen(false)}
            className="grid size-11 place-items-center rounded-lg lg:hidden"
            aria-label="Close menu"
          >
            <X size={20} aria-hidden="true" />
          </button>
        </div>

        <nav aria-label="Main" className="flex flex-col gap-0.5 overflow-y-auto">
          {NAV_ITEMS.map(({ label, path, icon: Icon }) => (
            <NavLink
              key={path}
              to={path}
              end={path === '/'}
              onClick={() => setMenuOpen(false)} // close the mobile drawer after choosing a page
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors ${
                  isActive ? 'bg-accent font-semibold text-accent-ink' : 'text-muted hover:bg-surface hover:text-ink'
                }`
              }
            >
              <Icon size={18} aria-hidden="true" />
              {label}
            </NavLink>
          ))}
        </nav>

        <UserCard />
      </aside>

      <main className="min-w-0 flex-1 px-4 py-6 sm:px-8">
        <Outlet />
      </main>
    </div>
  )
}

function UserCard() {
  const { user, logout } = useAuth()
  // Hooks must run on every render, so this goes before the early return below
  const [signingOut, setSigningOut] = useState(false)
  if (!user) return null

  function signOut() {
    setSigningOut(true)
    // No need to set it back: when logout finishes, this whole layout is replaced by the login page
    void logout()
  }

  return (
    <div className="mt-auto flex flex-col gap-3 rounded-xl border border-line bg-surface p-3.5">
      <div className="flex items-center gap-3">
        <span className="grid size-9 shrink-0 place-items-center rounded-full bg-surface-2 text-sm font-semibold" aria-hidden="true">
          {user.name.charAt(0)}
        </span>
        <div className="min-w-0">
          <div className="truncate text-sm font-semibold">{user.name}</div>
          <div className="text-xs text-muted">
            {user.role_label}
            {user.is_demo && ' · Demo'}
          </div>
        </div>
      </div>
      <button
        type="button"
        onClick={signOut}
        disabled={signingOut}
        className="flex h-11 items-center justify-center gap-2 rounded-lg border border-line-strong text-sm font-medium hover:bg-surface-2 disabled:opacity-70"
      >
        {signingOut ? <Spinner /> : <LogOut size={16} aria-hidden="true" />}
        {signingOut ? 'Signing out…' : 'Sign out'}
      </button>
    </div>
  )
}
