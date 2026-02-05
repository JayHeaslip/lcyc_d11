import { defineConfig } from 'vite'

export default defineConfig({
  base: './', // Good for Drupal subfolder

  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    manifest: true,
    rollupOptions: {
      input: {
        main: './src/scss/main.style.scss',
        app: './src/js/main.script.js',
        bootstrap: './src/scss/_bootstrap.scss',
      },
    },
  },

  css: {
    devSourcemap: true,
    preprocessorOptions: {
      scss: {
        additionalData: `@use "sass:math";`,
        silenceDeprecations: ['import', 'if-function', 'color-functions', 'global-builtin'],
      },
    },
  },

  server: {
    host: '0.0.0.0', // Required in Docker/DDEV
    port: 5173,
    strictPort: true, // Keeps port consistent — good!

    // Use DDEV's primary URL dynamically (safer than hardcoding)
    origin: `${process.env.DDEV_PRIMARY_URL || 'https://d11.lcyc.info.ddev.site'}:5173`,

    // Better HMR config for DDEV (avoids websocket connection failures)
    hmr: {
      host: 'd11.lcyc.info.ddev.site', // Or use process.env.DDEV_HOSTNAME if available
      protocol: 'wss', // Use secure websocket since DDEV is HTTPS
      clientPort: 5173, // Ensures browser connects to the exposed port
      // overlay: true,                  // Optional: show errors in browser overlay
    },

    // Polling often needed in Docker for file watching reliability
    watch: {
      usePolling: true,
      interval: 1000, // Optional: tune if too CPU heavy
    },

    // Proxy is usually NOT needed for Vite in DDEV — remove unless you have a specific reason
    // (Vite serves assets directly; Drupal requests them via <script src="https://...ddev.site:5173/...">)
    // proxy: { ... }  ← Comment out or delete this block

    // Add CORS to allow requests from your main DDEV site (helps with @vite/client etc.)
    cors: {
      origin: ['https://d11.lcyc.info.ddev.site', /https?:\/\/.*\.ddev\.site(:\d+)?$/],
      credentials: true,
    },
  },
})
