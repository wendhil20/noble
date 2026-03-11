// index.js
// Entry point ng Node.js server
// Sa Hostinger: itong file ang dapat i-set as entry point (hindi server.js)

require('dotenv').config();

const express    = require('express');
const http       = require('http');
const { Server } = require('socket.io');
const mysql      = require('mysql2');
const cors       = require('cors');

const app    = express();
const server = http.createServer(app);

// ─── CORS ─────────────────────────────────────────────────────────────────
app.use(cors({
  origin: [
    'https://noblehomedepot.com',
    'https://www.noblehomedepot.com',
    'http://localhost',
    'http://127.0.0.1'
  ],
  credentials: true
}));

app.use(express.json());

// ─── Socket.io ────────────────────────────────────────────────────────────
const io = new Server(server, {
  cors: {
    origin: [
      'https://noblehomedepot.com',
      'https://www.noblehomedepot.com',
      'http://localhost',
      'http://127.0.0.1'
    ],
    methods:     ['GET', 'POST'],
    credentials: true
  }
});

// ─── Database ─────────────────────────────────────────────────────────────
const db = mysql.createPool({
  host:     process.env.DB_HOST     || 'srv596.hstgr.io',
  user:     process.env.DB_USER     || 'u318146187_nh',
  password: process.env.DB_PASS     || '',
  database: process.env.DB_NAME     || 'u318146187_nh',
  waitForConnections: true,
  connectionLimit:    10
});

// ─── Load Modules ─────────────────────────────────────────────────────────
require('./chat')(io, db);
const { sendNotification } = require('./notif')(io, db);

// ─── API: Send Notification ───────────────────────────────────────────────
app.post('/api/send-notif', async (req, res) => {
  const secret = req.headers['x-api-secret'];
  if (secret !== (process.env.API_SECRET || 'noble-secret-2025')) {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }

  const { userId, actorId, type, title, message, link } = req.body;

  if (!userId || !title || !message) {
    return res.status(400).json({ success: false, message: 'Missing required fields' });
  }

  const result = await sendNotification({ userId, actorId, type, title, message, link });
  res.json(result);
});

// ─── Health Check ─────────────────────────────────────────────────────────
app.get('/', (req, res) => {
  res.json({ status: 'Noble Home Server running ✅', time: new Date() });
});

// ─── Socket: User Rooms ───────────────────────────────────────────────────
io.on('connection', (socket) => {
  console.log(`[IO] Connected: ${socket.id}`);

  socket.on('join', (userId) => {
    if (userId) {
      socket.join(`user_${userId}`);
      console.log(`[IO] User ${userId} joined room`);
    }
  });

  socket.on('disconnect', () => {
    console.log(`[IO] Disconnected: ${socket.id}`);
  });
});

// ─── Start Server ─────────────────────────────────────────────────────────
const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`🚀 Noble Home Server running on port ${PORT}`);
});