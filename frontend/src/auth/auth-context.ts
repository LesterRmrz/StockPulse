import { createContext, useContext } from 'react'
import type { Role, User } from '../lib/types'

export type AuthStatus = 'loading' | 'authenticated' | 'guest'

export interface AuthContextValue {
  user: User | null
  /** 'loading' only while we ask the server "who am I?" on page load */
  status: AuthStatus
  login: (email: string, password: string, remember: boolean) => Promise<void>
  demoLogin: (role: Extract<Role, 'manager' | 'staff'>) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

/** Read the logged-in user and auth actions from any component. */
export function useAuth(): AuthContextValue {
  const value = useContext(AuthContext)
  if (!value) throw new Error('useAuth must be used inside <AuthProvider>')
  return value
}
