import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [
    laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx',
                'resources/js/home.tsx',
                'resources/js/navbar.tsx',
                'resources/js/dashboard.tsx',
                'resources/js/auth/login.tsx',
                'resources/js/auth/register.tsx',
                'resources/js/admin/invites.tsx',
                'resources/js/admin/seasons/index.tsx',
                'resources/js/admin/users.tsx',
                'resources/js/admin/season-pass-requests.tsx',
                'resources/js/admin/email-log.tsx',
                'resources/js/request/index.tsx',
            ],
      refresh: true,
    }),
    react(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
      'react': path.resolve(__dirname, 'node_modules/react'),
      'react-dom': path.resolve(__dirname, 'node_modules/react-dom'),
    },
  },
  build: {
    rollupOptions: {
      external: (id) => /\.test\.[tj]sx?$/.test(id) || id.includes('/__tests__/'),
    }
  }
});
