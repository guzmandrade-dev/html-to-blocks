const { chromium } = require("@playwright/test");

/**
 * Parse command line arguments
 * @returns {Object} Parsed arguments
 */
function parseArgs() {
    const args = process.argv.slice(2);
    const parsed = {
        url: null,
        language: null,
        selector: 'body'
    };

    // First argument should be the URL
    if (args.length > 0 && !args[0].startsWith('--')) {
        parsed.url = args[0];
    }

    // Parse named arguments
    args.forEach(arg => {
        if (arg.startsWith('--language=')) {
            parsed.language = arg.split('=')[1];
        } else if (arg.startsWith('--selector=')) {
            parsed.selector = arg.split('=')[1];
        }
    });

    return parsed;
}

/**
 * Runs the function to launch a Playwright browser, open a new page,
 * navigate to a specific URL, scroll to the bottom of the page,
 * retrieve the page source, and close the browser.
 *
 * @return {Promise<void>}
 */
async function run() {
    const { url, language, selector } = parseArgs();

    if (!url) {
        console.error('Usage: node getRemoteHTML.js <url> [--language=LANGUAGE] [--selector=SELECTOR]');
        process.exit(1);
    }

    const browser = await chromium.launch({
        headless: true,
    });
    const context = await browser.newContext({
        userAgent:
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36",
    });
    const page = await context.newPage();

    // go to the main url
    await page.goto(url);

    let backToHome = false;

    // change language if specified.
    if (language) {
        let newLangURL = await page
            .locator("a[data-lang=" + language + "]")
            .getAttribute("href")
            .catch(() => "/");
        let jump_url = new URL(page.url());
        backToHome = newLangURL === "/" + language && "https://www.domain.com" !== url;
        await page.goto(jump_url.origin + newLangURL);
    }

    await page.setViewportSize({
        width: 1200,
        height: 800,
    });
    await autoScroll(page);

    // Apply inline styles to all elements based on computed styles from page stylesheets
    await page.evaluate((selector) => {
        // Get all stylesheets from the current page
        const pageStylesheets = Array.from(document.styleSheets).filter(sheet => {
            try {
                // Only include stylesheets from the same origin or inline styles
                return !sheet.href || sheet.href.startsWith(window.location.origin);
            } catch (e) {
                return false;
            }
        });

        // Function to get styles that come from page stylesheets (not browser defaults)
        function getPageStyles(element) {
            const computedStyles = window.getComputedStyle(element);
            const pageStyles = {};
            
            // Properties to check (common CSS properties that are likely to be set by page styles)
            const relevantProperties = [
                'color', 'background-color', 'background-image', 'background', 'font-family', 'font-size', 
                'font-weight', 'font-style', 'text-align', 'text-decoration', 'line-height',
                'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
                'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
                'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
                'border-color', 'border-width', 'border-style', 'border-radius',
                'width', 'height', 'max-width', 'max-height', 'min-width', 'min-height',
                'display', 'position', 'top', 'right', 'bottom', 'left', 'z-index',
                'float', 'clear', 'overflow', 'visibility', 'opacity',
                'flex', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items',
                'grid', 'grid-template', 'grid-gap', 'transform', 'transition'
            ];

            relevantProperties.forEach(prop => {
                const value = computedStyles.getPropertyValue(prop);
                if (value && value !== 'initial' && value !== 'inherit' && value !== 'unset') {
                    // Check if this style likely comes from a stylesheet (not browser default)
                    if (hasCustomStyle(element, prop, value)) {
                        pageStyles[prop] = value;
                    }
                }
            });

            return pageStyles;
        }

        // Helper function to determine if a style is likely from page CSS (not browser default)
        function hasCustomStyle(element, property, value) {
            // Create a temporary element to compare against browser defaults
            const temp = document.createElement(element.tagName.toLowerCase());
            temp.style.display = 'none';
            document.body.appendChild(temp);
            const defaultValue = window.getComputedStyle(temp).getPropertyValue(property);
            document.body.removeChild(temp);
            
            return value !== defaultValue;
        }

        // Apply inline styles to all elements in the target element
        function applyInlineStyles(element) {
            const styles = getPageStyles(element);
            const styleString = Object.entries(styles)
                .map(([prop, value]) => `${prop}: ${value}`)
                .join('; ');
            
            if (styleString) {
                element.setAttribute('style', styleString);
            }

            // Recursively apply to children
            Array.from(element.children).forEach(child => applyInlineStyles(child));
        }

        // Start with the selected element
        const targetElement = document.querySelector(selector);
        if (targetElement) {
            applyInlineStyles(targetElement);
        }
    }, selector);

    // Get the content of the selected element
    let elementContent = await page.locator(selector).innerHTML();
    const tagName = await page.locator(selector).evaluate(el => el.tagName.toLowerCase());
    
    // We append the source URL to the element since we want to retrieve it later to build the `slug`.
    console.log(`<${tagName}>` + elementContent.replace(`</${tagName}>`, `<span data-origin-url="${page.url()}" /></${tagName}>`) + `</${tagName}>`);
    
    await browser.close();
}

/**
 * Scrolls the page to the bottom automatically.
 *
 * @param {Object} page - The page object.
 * @return {Promise} A promise that resolves when the scrolling is complete.
 */
async function autoScroll(page) {
    await page.evaluate(async () => {
        await new Promise((resolve) => {
            var totalHeight = 0;
            var distance = 100;
            var timer = setInterval(() => {
                var scrollHeight = document.body.scrollHeight;
                window.scrollBy(0, distance);
                totalHeight += distance;

                if (totalHeight >= scrollHeight - window.innerHeight) {
                    clearInterval(timer);
                    resolve();
                }
            }, 100);
        });
    });
}

run();
