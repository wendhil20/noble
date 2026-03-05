// ============================================
//  MESSENGER - Express + Socket.io Server
//  Run: node server.js
//  Port: process.env.PORT || 3000
// ============================================

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const path = require('path');

const app = express();
// Serve public folder (your HTML/CSS/JS files)
app.use(express.static(path.join(__dirname, 'public')));
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

const httpServer = http.createServer(app);

const io = new Server(httpServer, {
  cors: {
    origin: '*', // Allow XAMPP (localhost) to connect
    methods: ['GET', 'POST']
  }
});

// In-memory message store (resets on server restart)
// Para persistent, palitan mo ng MySQL (see comments below)
const messages = [];
const onlineUsers = {};

io.on('connection', (socket) => {
  console.log(`✅ User connected: ${socket.id}`);

  // ── 1. USER JOINS ──────────────────────────────────────
  socket.on('user:join', (username) => {
    socket.username = username;
    onlineUsers[socket.id] = username;

    console.log(`👤 ${username} joined`);

    // Send existing message history to the newly joined user
    socket.emit('history', messages);

    // Broadcast updated online users list to everyone
    io.emit('users:online', Object.values(onlineUsers));

    // Notify others that someone joined
    socket.broadcast.emit('user:joined', {
      username,
      timestamp: new Date().toISOString()
    });
  });

  // ── 2. SEND MESSAGE ────────────────────────────────────
  socket.on('message:send', (data) => {
    const message = {
      id: Date.now(),
      user: socket.username || 'Anonymous',
      text: data.text,
      timestamp: new Date().toISOString()
    };

    // Save to in-memory store
    messages.push(message);

    // Keep only last 100 messages in memory
    if (messages.length > 100) messages.shift();

    console.log(`💬 [${message.user}]: ${message.text}`);

    // Broadcast to ALL connected users (including sender)
    io.emit('message:new', message);

    /*
    ── OPTIONAL: Save to MySQL instead ──────────────────
    const mysql = require('mysql2');
    const db = mysql.createConnection({
      host: 'localhost', user: 'root', password: '', database: 'messenger'
    });
    db.query(
      'INSERT INTO messages (user, text, timestamp) VALUES (?, ?, NOW())',
      [message.user, message.text]
    );
    */
  });

  // ── 3. TYPING INDICATOR ────────────────────────────────
  socket.on('typing:start', () => {
    socket.broadcast.emit('typing:show', socket.username);
  });

  socket.on('typing:stop', () => {
    socket.broadcast.emit('typing:hide', socket.username);
  });

  // ── 4. DISCONNECT ──────────────────────────────────────
  socket.on('disconnect', () => {
    const username = onlineUsers[socket.id];
    delete onlineUsers[socket.id];

    console.log(`❌ ${username || socket.id} disconnected`);

    io.emit('users:online', Object.values(onlineUsers));

    if (username) {
      io.emit('user:left', {
        username,
        timestamp: new Date().toISOString()
      });
    }
  });
});

const PORT = process.env.PORT || 3000;
httpServer.listen(PORT, () => {
  console.log(`\n🚀 Server running on port ${PORT}`);
  console.log(`📡 Waiting for connections...\n`);
});