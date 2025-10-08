# HTML To Blocks (WordPress Plugin)

Fetch remote HTML fragment with computed styles using Playwright.

Requirements:
- Node.js installed on server
- Run: cd wp-content/plugins/html-to-blocks/node && npm install

Usage:
1. Activate plugin
2. Tools > HTML To Blocks
3. Enter URL, selector, optional language
4. Fetch and copy HTML

REST:
GET /wp-json/html2blocks/v1/fetch?url=...&selector=main&language=es
Auth: Must be logged-in user with edit_posts.
