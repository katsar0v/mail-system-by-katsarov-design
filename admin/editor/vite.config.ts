import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: '../js/editor',
    emptyOutDir: true,
    lib: {
      entry: resolve(__dirname, 'src/index.tsx'),
      name: 'MSKDVisualEditor',
      fileName: 'visual-editor',
      formats: ['iife'],
    },
    rollupOptions: {
      output: {
        assetFileNames: 'visual-editor.[ext]',
        entryFileNames: 'visual-editor.js',
      },
    },
    sourcemap: false,
    minify: 'terser',
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
});
