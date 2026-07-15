import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // host 0.0.0.0 — иначе dev-сервер в контейнере не виден с хоста.
    host: true,
    port: 5175,
    strictPort: true,
    // HMR (авто-перезагрузка) должен стучаться на проброшенный порт хоста.
    hmr: { clientPort: 5175 },
    // Запросы к /api фронт проксирует на бэкенд (nginx в docker-сети),
    // чтобы локально не упираться в CORS. На проде /api отдаёт тот же домен.
    proxy: {
      '/api': {
        target: 'http://nginx:80',
        changeOrigin: true,
      },
    },
  },
  // Прод-сборка кладётся в public/ Symfony (см. деплой). Базовый путь — корень.
  build: {
    outDir: 'dist',
  },
})
