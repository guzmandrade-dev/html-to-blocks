# HTML To Blocks (WordPress Plugin)

Fetches a remote HTML fragment (including computed styles via Playwright) and returns HTML that can be reused in WordPress blocks.

## Requirements

- Node.js installed on the server/environment
- Install Node dependencies for the plugin service:

```bash
cd wp-content/plugins/html-to-blocks/node
npm install
```

## Local development (`wp-env`)

`.wp-env.json` defines how the local server environment is set up for this plugin.

It includes the setup used to:

1. Parse the target URL with a basic server flow
2. Produce/output the resulting HTML
3. Return that HTML to the plugin so it can be copied/used in WordPress blocks

Check the `wp-env` config files in this repository for the exact local service wiring and ports.

### Node Service Commands

Run from the plugin root:

```bash
npm start --prefix node
npm run stop --prefix node
npm run restart --prefix node
```

The Node service accepts these environment variables:

- `PORT` (default: `3001`)
- `BIND_HOST` (default: `0.0.0.0`)

Example (explicit Docker/WSL-friendly binding):

```bash
BIND_HOST=0.0.0.0 PORT=3001 npm start --prefix node
```

`npm run stop --prefix node` is safe to run repeatedly. If the service is already stopped, it exits without failing.

## Usage

1. Activate the plugin
2. Go to **Tools > HTML To Blocks**
3. Enter:
   - URL
   - CSS selector
   - Optional language
4. Click fetch and copy the returned HTML

## REST API

**Endpoint**

`GET /wp-json/html2blocks/v1/fetch?url=...&selector=main&language=es`

**Auth**

Must be a logged-in user with `edit_posts` capability.
