import dotenv from 'dotenv';
dotenv.config();

import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import { createServer as createViteServer } from 'vite';
import { handleApiFallback } from './apiFallback.ts';
import { testDatabaseConnection } from './db.ts';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function startServer() {
  const app = express();
  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));

  const PORT = 3000;

  // Initial DB check
  testDatabaseConnection().then(dbTest => {
    if (dbTest.connected) {
      console.log(`[SATA Server] Database verified: ${dbTest.message}`);
    } else {
      console.log(`[SATA Server] Database check: ${dbTest.message}`);
    }
  });

  // Direct API Handlers for SATA Admin
  const apiHandler = async (req: express.Request, res: express.Response) => {
    try {
      await handleApiFallback(req, res, req.originalUrl || req.url);
    } catch (err: any) {
      console.error('[API Gateway Error]', err);
      if (!res.headersSent) {
        res.status(500).json({
          success: false,
          message: 'Internal Gateway Error',
          error: err.message,
        });
      }
    }
  };

  // Route: /api/* -> Direct SATA MySQL API Gateway
  app.use('/api', apiHandler);

  // Route: /admin/api/* -> Direct SATA MySQL API Gateway
  app.use('/admin/api', apiHandler);

  // Direct Admin Auth endpoints
  app.post('/admin/login', apiHandler);
  app.post('/admin/logout', apiHandler);

  // Storage / Media mocks
  app.use('/storage', express.static(path.resolve(__dirname, 'storage', 'app', 'public')));

  // Vite development middleware for frontend
  const isProd = process.env.NODE_ENV === 'production';
  if (!isProd) {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa',
    });
    app.use(vite.middlewares);
  } else {
    app.use(express.static(path.resolve(__dirname, 'dist')));
    app.get('*', (_req, res) => {
      res.sendFile(path.resolve(__dirname, 'dist', 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`[SATA Server] Unified Full-Stack Gateway running on http://0.0.0.0:${PORT}`);
  });
}

startServer().catch((err) => {
  console.error('[SATA Server] Fatal startup error:', err);
  process.exit(1);
});
