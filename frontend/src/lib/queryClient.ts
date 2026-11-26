import { QueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'

/**
 * TanStack Query keeps every API response in a cache, keyed by an array like
 * ['products', { page: 2 }]. Components that ask for the same key share one
 * request and one cached result.
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Data counts as fresh for 30 s: switching pages back and forth within
      // that time shows the cached copy instantly instead of refetching.
      staleTime: 30_000,
      // Retry network hiccups once, but never 4xx errors (they won't fix themselves).
      retry: (failureCount, error) => {
        const status = isAxiosError(error) ? error.response?.status : undefined
        return failureCount < 1 && (status === undefined || status >= 500)
      },
      refetchOnWindowFocus: false,
    },
  },
})
