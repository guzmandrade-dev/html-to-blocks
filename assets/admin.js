(function () {
    const form = document.getElementById("html2blocks-form");
    const htmlBox = document.getElementById("html2blocks-fragment");
    const blocksBox = document.getElementById("html2blocks-blocks");
    const copyHtmlBtn = document.getElementById("html2blocks-copy-html");
    const copyBlocksBtn = document.getElementById("html2blocks-copy-blocks");

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        htmlBox.textContent = "Fetching...";
        blocksBox.value = "";

        const url = document.getElementById("h2b-url").value.trim();
        const language = document.getElementById("h2b-language").value.trim();
        const selector = document.getElementById("h2b-selector").value.trim() || "body";

        const params = new URLSearchParams({ url, selector });
        if (language) params.append("language", language);

        try {
            const res = await fetch(HTML2BLOCKS_DATA.rest + "?" + params.toString(), {
                headers: { "X-WP-Nonce": HTML2BLOCKS_DATA.nonce },
            });
            if (!res.ok) {
                const t = await res.text();
                throw new Error(t || "Request failed");
            }
            const data = await res.json();

            htmlBox.textContent = "";
            htmlBox.innerHTML = data.html || "";
            blocksBox.value = data.blocks || "";

            copyHtmlBtn.onclick = () => {
                navigator.clipboard.writeText(data.html || "").then(() => {
                    copyHtmlBtn.textContent = "Copied!";
                    setTimeout(() => (copyHtmlBtn.textContent = "Copy HTML"), 1500);
                });
            };

            copyBlocksBtn.onclick = () => {
                navigator.clipboard.writeText(data.blocks || "").then(() => {
                    copyBlocksBtn.textContent = "Copied!";
                    setTimeout(() => (copyBlocksBtn.textContent = "Copy Blocks"), 1500);
                });
            };
        } catch (err) {
            htmlBox.textContent = "Error: " + err.message;
            blocksBox.value = "";
        }
    });
})();
