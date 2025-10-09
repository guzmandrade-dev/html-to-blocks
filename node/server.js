const express = require('express');
const cors = require('cors');
const { fetchFragment } = require('./getRemoteHTML');

const app = express();
app.use(express.json({ limit: '1mb' }));
app.use(express.urlencoded({ extended: true, limit: '1mb' }));
app.use(cors()); // optional; handy during testing

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
app.listen(PORT, () => {
  console.log(`html-to-blocks server listening on http://localhost:${PORT}`);
});