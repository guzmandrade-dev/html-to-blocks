const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const { fetchFragment } = require('./getRemoteHTML');

const app = express();
app.use(express.json({ limit: '1mb' }));
app.use(express.urlencoded({ extended: true, limit: '1mb' }));
app.use(cors()); // optional; handy during testing

const PID_FILE = path.join(__dirname, '.server.pid');
let isShuttingDown = false;

function writePidFile() {
  fs.writeFileSync(PID_FILE, String(process.pid), 'utf8');
}

function removePidFile() {
  if (fs.existsSync(PID_FILE)) {
    fs.unlinkSync(PID_FILE);
  }
}

app.get('/health', (_req, res) => res.status(200).send('ok'));

app.post('/fetch', async (req, res) => {
  const url = (req.body.url || '').trim();
  const selector = (req.body.selector || 'body').trim() || 'body';
  const language = (req.body.language || '').trim() || null;

  if (!url) return res.status(400).type('text/plain').send('Missing url');

  try {
    const html = await fetchFragment({ url, language, selector });
    // Return raw HTML fragment
    res.status(200).type('text/html').send(html);
  } catch (err) {
    res.status(500).type('text/plain').send(err?.message || 'Internal error');
  }
});

const PORT = process.env.PORT || 3001;
const HOST = (process.env.BIND_HOST || process.env.HOST || '0.0.0.0').trim();
const displayHost = HOST === '0.0.0.0' ? 'localhost' : HOST;

const server = app.listen(PORT, HOST, () => {
  writePidFile();
  console.log(
    `html-to-blocks server listening on http://${displayHost}:${PORT} (bound to ${HOST})`
  );
});

function shutdown(signal) {
  if (isShuttingDown) {
    return;
  }

  isShuttingDown = true;
  console.log(`Received ${signal}; shutting down html-to-blocks server...`);

  // Avoid hanging forever if there are open handles.
  const forceExitTimer = setTimeout(() => {
    console.error('Graceful shutdown timed out; forcing exit');
    removePidFile();
    process.exit(1);
  }, 10000);

  server.close(() => {
    clearTimeout(forceExitTimer);
    removePidFile();
    console.log('html-to-blocks server stopped');
    process.exit(0);
  });
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
