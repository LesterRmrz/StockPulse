import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, fetchCsrfCookie } from '../lib/api'
import type { Resource, User } from '../lib/types'
import { AuthContext, type AuthContextValue, type AuthStatus } from './auth-context'

/**
 * Holds "who is logged in" for the whole app.
 *
 * The session itself lives in an HttpOnly cookie that JavaScript can't read
 * (so an XSS bug can't steal it). That's why, on page load, we ask the API
 * GET /api/user to find out whether the cookie is still valid.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [status, setStatus] = useState<AuthStatus>('loading')

  const signedIn = useCallback((u: User) => {
    setUser(u)
    setStatus('authenticated')
  }, [])

  const signedOut = useCallback(() => {
    setUser(null)
    setStatus('guest')
  }, [])

  // On first load: are we already logged in?
  useEffect(() => {
    api
      .get<Resource<User>>('/user')
      .then((res) => signedIn(res.data.data))
      .catch(() => signedOut())
  }, [signedIn, signedOut])

  // If any request later comes back 401 (session expired), drop to the login page.
  useEffect(() => {
    const id = api.interceptors.response.use(undefined, (error) => {
      if (error?.response?.status === 401) signedOut()
      return Promise.reject(error)
    })
    return () => api.interceptors.response.eject(id)
  }, [signedOut])

  const login = useCallback<AuthContextValue['login']>(
    async (email, password, remember) => {
      await fetchCsrfCookie()
      const res = await api.post<Resource<User>>('/login', { email, password, remember })
      signedIn(res.data.data)
    },
    [signedIn],
  )

  const demoLogin = useCallback<AuthContextValue['demoLogin']>(
    async (role) => {
      await fetchCsrfCookie()
      const res = await api.post<Resource<User>>('/demo-login', { role })
      signedIn(res.data.data)
    },
    [signedIn],
  )

  const logout = useCallback(async () => {
    try {
      await api.post('/logout')
    } finally {
      signedOut()
    }
  }, [signedOut])

  const value = useMemo(
    () => ({ user, status, login, demoLogin, logout }),
    [user, status, login, demoLogin, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
