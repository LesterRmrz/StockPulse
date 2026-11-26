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
  if (status === 403) return 'You don\'t have permission to do this.'
  if (!error.response) return 'Cannot reach the server. Is the backend running?'

  return data?.message || 'Something went wrong. Please try again.'
}

/**
 * Laravel's 422 errors as { field: firstMessage }, so a form can show each
 * message under its own input. Nested keys stay as-is ("items.0.quantity").
 */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!isAxiosError(error) || error.response?.status !== 422) return {}

  const errors = (error.response.data as { errors?: Record<string, string[]> }).errors ?? {}
  return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]))
}

/**
 * Drops empty filters so the URL stays short: { search: '', page: 2 } -> { page: 2 }.
 * Record<string, unknown> means "an object with string keys and any values".
 */
export function cleanParams(params: object): Record<string, unknown> {
  return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== undefined && value !== null))
}
