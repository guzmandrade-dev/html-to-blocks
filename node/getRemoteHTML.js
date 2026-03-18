const { chromium } = require('@playwright/test');

/**
 * Fetch a styled fragment from a remote page.
 * @param {Object} opts
 * @param {string} opts.url
 * @param {string|null} opts.language
 * @param {string} opts.selector
 * @returns {Promise<string>}
 */
async function fetchFragment({ url, language = null, selector = 'body' }) {
    if (!url) throw new Error('Missing URL');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121 Safari/537.36"
    });
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

    if (language) {
        try {
            const langLink = await page.locator(`a[data-lang=${language}]`).getAttribute('href').catch(() => null);
            if (langLink) {
                const base = new URL(page.url());
                await page.goto(base.origin + langLink, { waitUntil: 'domcontentloaded' });
            }
        } catch (_) {}
    }

    await page.setViewportSize({ width: 1280, height: 900 });
    await autoScroll(page);

    // Inline computed styles (filtered) into target subtree
    await page.evaluate((sel) => {
        const target = document.querySelector(sel);
        if (!target) return;

        const relevantProperties = [
            'color','background','background-color','background-image','font-family','font-size','font-weight','font-style',
            'text-align','text-decoration','line-height','margin','margin-top','margin-right','margin-bottom','margin-left',
            'padding','padding-top','padding-right','padding-bottom','padding-left','border','border-top','border-right',
            'border-bottom','border-left','border-color','border-width','border-style','border-radius','width','height',
            'max-width','min-width','display','position','top','right','bottom','left','z-index','overflow','visibility',
            'opacity','flex','flex-direction','flex-wrap','justify-content','align-items','gap','grid','grid-template-columns',
            'grid-template-rows','grid-column','grid-row','transform','transition','box-shadow'
        ];

        function compute(element) {
            const cs = window.getComputedStyle(element);
            const styleObj = {};
            relevantProperties.forEach(p => {
                const v = cs.getPropertyValue(p);
                if (v && v !== 'initial' && v !== 'normal' && v !== 'none' && v.trim() !== '') {
                    styleObj[p] = v;
                }
            });
            return styleObj;
        }

        function applyRecursive(el) {
            const styles = compute(el);
            if (Object.keys(styles).length) {
                const merged = Object.entries(styles).map(([k,v]) => `${k}: ${v}`).join('; ');
                el.setAttribute('style', merged);
            }
            Array.from(el.children).forEach(c => applyRecursive(c));
        }

        applyRecursive(target);
    }, selector);

    const exists = await page.locator(selector).count();
    if (!exists) {
        await browser.close();
        throw new Error('Selector not found');
    }

    const tagName = await page.locator(selector).evaluate(el => el.tagName.toLowerCase());
    const inner = await page.locator(selector).innerHTML();
    const fragment = `<${tagName} data-origin-url="${page.url()}">${inner}</${tagName}>`;

    await browser.close();
    return fragment;
}

async function autoScroll(page) {
    await page.evaluate(async () => {
        await new Promise(resolve => {
            let total = 0;
            const step = 400;
            const timer = setInterval(() => {
                window.scrollBy(0, step);
                total += step;
                if (total >= document.body.scrollHeight - window.innerHeight) {
                    clearInterval(timer);
                    resolve();
                }
            }, 120);
        });
    });
}

// Allow CLI fallback for debugging:
// node getRemoteHTML.js --url="https://example.com" --selector="main" --language=es
if (require.main === module) {
    (async () => {
        const args = process.argv.slice(2);
        const get = (name, def = null) => {
            const found = args.find(a => a.startsWith(`--${name}=`));
            return found ? found.split('=').slice(1).join('=') : def;
        };
        const url = get('url');
        const language = get('language');
        const selector = get('selector', 'body');
        if (!url) {
            console.error('Usage: node getRemoteHTML.js --url=URL [--language=LANG] [--selector=CSS]');
            process.exit(1);
        }
        try {
            const html = await fetchFragment({ url, language, selector });
            process.stdout.write(html);
        } catch (e) {
            console.error('Error:', e.message);
            process.exit(2);
        }
    })();
}

module.exports = { fetchFragment };
