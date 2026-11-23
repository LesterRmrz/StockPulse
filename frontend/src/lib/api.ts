import axios, { isAxiosError } from 'axios'

/**
 * One configured HTTP client for the whole app.
 *
 * - baseURL '/api': Vite's dev server proxies /api to Laravel, so the browser
 *   thinks the API is on the same origin as the app (no CORS setup needed).
 * - withCredentials: send the session cookie with every request.
 * - withXSRFToken: copy the XSRF-TOKEN cookie into the X-XSRF-TOKEN header,
 *   which is how Laravel checks requests really come from our app (CSRF protection).
 */
export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** Asks Laravel to set the XSRF-TOKEN cookie. Call before login. */
export function fetchCsrfCookie() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

/**
 * Turns an API error into a sentence for the UI.
 * 422 = validation errors, 429 = too many attempts.
 */
export function errorMessage(error: unknown): string {
  if (!isAxiosError(error)) return 'Something went wrong. Please try again.'

  const status = error.response?.status
  const data = error.response?.data as
    | { message?: string; errors?: Record<string, string[]> }
    | undefined

  if (status === 429) return 'Too many attempts. Wait a minute and try again.'
  if (status === 422 && data?.errors) return Object.values(data.errors)[0]?.[0] ?? 'Please check the form.'
  if (!error.response) return 'Cannot reach the server. Is the backend running?'

  return data?.message || 'Something went wrong. Please try again.'
}
