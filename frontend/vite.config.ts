import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: true,        // listen on 0.0.0.0 so Docker can expose it
    port: 5173,
    strictPort: true,  // fail instead of silently switching ports
    watch: {
      // Windows files mounted into Docker don't send change events,
      // so Vite checks for changes itself (needed for hot reload)
      usePolling: true,
    },
    proxy: {
      // Any request to /api/... is forwarded to Laravel.
      // "php" is the compose service name, reachable inside Docker's network.
      '/api': {
        target: 'http://php:8000',
        changeOrigin: true,
      },
    },
  },
})
