const express    = require('express');
const http       = require('http');
const { Server } = require('socket.io');
const mysql      = require('mysql2/promise');

// ── APP SETUP ─────────────────────────────────────────────
const app        = express();
const httpServer = http.createServer(app);
const io         = new Server(httpServer, { cors: { origin: '*' } });

// ── DATABASE ──────────────────────────────────────────────
const db = mysql.createPool({
  host:     process.env.DB_HOST || 'localhost',
  user:     process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'noble',
  waitForConnections: true,
  connectionLimit: 10
});

// ── LOAD MODULES ──────────────────────────────────────────
require('./chat')(io, db);
// require('./notif')(io, db);   // <- buksan mo lang ito pag handa na ang notif

// ── START SERVER ──────────────────────────────────────────
const PORT = process.env.PORT || 3000;
httpServer.listen(PORT, () => console.log(`🚀 Noble Server running on port ${PORT}`));