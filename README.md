# HTML To Blocks (WordPress Plugin)

Fetches a remote HTML fragment (including computed styles via Playwright) and returns HTML that can be reused in WordPress blocks.

## Requirements

- **WordPress 7.0 or later** — required for the AI conversion features (WordPress AI client APIs).
- Node.js installed on the server/environment
- Install Node dependencies for the plugin service:

```bash
cd wp-content/plugins/html-to-blocks/node
npm install
```

## Local development (`wp-env`)

> **WordPress 7.0 / Beta requirement**
> The AI conversion features require WordPress 7.0. Since 7.0 is not yet released, you need to run a beta build.
>
> The `.wp-env.json` in this repository already includes the [WordPress Beta Tester](https://wordpress.org/plugins/wordpress-beta-tester/) plugin. After starting wp-env, go to **Tools > Beta Testing** and select **Beta/RC** (or **Bleeding edge nightly**) to switch Core to the latest beta.
>
> If you are **not** using `wp-env`, ensuring the correct WordPress version installed is your responsibility.

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

### Fetch Endpoint

**Endpoint**

`GET /wp-json/html2blocks/v1/fetch`

**Auth**

Must be a logged-in user with `edit_posts` capability.

**Parameters**

- `url` (required, string): The URL to fetch and convert.
- `selector` (optional, string): CSS selector to extract. Defaults to `body`.
- `language` (optional, string): Language code for content. Defaults to auto-detect.
- `use_ai` (optional, boolean-like): Use AI conversion (`1`, `true`, `yes`, `on`) instead of local DOM converter. Defaults to `false`.

**Examples**

Local DOM converter (default):
```
GET /wp-json/html2blocks/v1/fetch?url=https://example.com&selector=.main&language=en
```

AI-assisted conversion:
```
GET /wp-json/html2blocks/v1/fetch?url=https://example.com&selector=.main&language=en&use_ai=true
```

**Response Fields**

- `html`: The raw HTML extracted from the source URL.
- `blocks`: The converted WordPress block markup.
- `blocksError`: Error message (if conversion failed).
- `conversionMethod`: Either `converter` or `ai` (shows which method was used).
- `sourceUrl`: The source URL used.
- `selector`: The CSS selector applied.
- `language`: The language code processed.
