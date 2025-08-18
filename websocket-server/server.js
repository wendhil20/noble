// websocket-server/server.js
const { Server } = require("socket.io");

const io = new Server(3001, {

  cors: {
    origin: "*", // OK for localhost
  }
});

console.log("✅ WebSocket server running on port 3000");

io.on("connection", (socket) => {
  console.log("🟢 New connection:", socket.id);

  socket.on("send_message", (data) => {
    console.log("📨 Message from", socket.id, ":", data);
    io.emit("receive_message", data); // send to everyone
  });

  socket.on("disconnect", () => {
    console.log("🔴 Disconnected:", socket.id);
  });
});
