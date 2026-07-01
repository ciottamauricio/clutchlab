import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true, // listen on 0.0.0.0 so nginx can reach the dev server in the container
    port: 5173,
    strictPort: true,
    // The browser reaches Vite through nginx on :8080, so the HMR websocket
    // has to be told the public port or live-reload silently breaks.
    hmr: { clientPort: 8080 },
  },
})
