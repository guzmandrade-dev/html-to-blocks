const { chromium } = require("@playwright/test");

/**
 * Runs the function to launch a Playwright browser, open a new page,
 * navigate to a specific URL, scroll to the bottom of the page,
 * retrieve the page source, and close the browser.
 *
 * @return {Promise<void>}
 */
async function run() {
    const browser = await chromium.launch({
        headless: true,
    });
    const context = await browser.newContext({
        userAgent:
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36",
    });
    const page = await context.newPage();

    // go to the main url
    await page.goto(process.argv[2]);

    let backToHome = false;

    // change language if specified.
    if (process.argv[3]) {
        let newLangURL = await page
            .locator("a[data-lang=" + process.argv[3] + "]")
            .getAttribute("href")
            .catch(() => "/");
        let jump_url = new URL(page.url());
        backToHome = newLangURL === "/" + process.argv[3] && "https://www.domain.com" !== process.argv[2];
        await page.goto(jump_url.origin + newLangURL);
    }

    await page.setViewportSize({
        width: 1200,
        height: 800,
    });
    await autoScroll(page);

    // 404 don't redirect to a new page, instead, body has id of `error`.
    let bodyError = await page
        .locator("body#error")
        .getAttribute("id")
        .catch(() => {
            // do nothing but continue, this probably means the selector was not found, which is good.
        });
    if (!bodyError) {
        // if we switched languages but ended up on the homepage of the new language, don't output anything.
        if (!backToHome || process.argv[3] === undefined) {
            let source = await page.content();
            // We append the source URL to the body since we want to retrieve it later to build the `slug`.
            console.log(source.replace("</body>", '<span data-origin-url="' + page.url() + '" /></body>'));
        }
    }
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
