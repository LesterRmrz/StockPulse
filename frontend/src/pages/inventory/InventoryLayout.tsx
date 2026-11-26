import { ArrowLeftRight } from 'lucide-react'
import { Link, NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../../auth/auth-context'
import { buttonClass } from '../../components/buttonClass'
import { PageHeader } from '../../components/PageHeader'

/**
 * A layout route: the header and tabs stay put while <Outlet /> swaps between
 * /inventory (stock levels) and /inventory/transfers.
 */
export function InventoryLayout() {
  const { user } = useAuth()

  return (
    <>
      <PageHeader
        title="Inventory"
        subtitle="Stock per product and warehouse. Every change is recorded in the ledger."
        actions={
          user?.can.transfer_stock && (
            <Link to="/inventory/transfers/new" className={buttonClass('primary')}>
              <ArrowLeftRight size={16} aria-hidden="true" />
              New transfer
            </Link>
          )
        }
      />
      <nav aria-label="Inventory sections" className="mb-4 flex gap-1 border-b border-line">
        <Tab to="/inventory" end>
          Stock levels
        </Tab>
        <Tab to="/inventory/transfers">Transfers</Tab>
      </nav>
      <Outlet />
    </>
  )
}

function Tab({ to, end, children }: { to: string; end?: boolean; children: string }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        `-mb-px border-b-2 px-4 py-2.5 text-sm font-medium ${isActive ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink'}`
      }
    >
      {children}
    </NavLink>
  )
}
