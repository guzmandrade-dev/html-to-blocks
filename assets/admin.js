(function () {
    const form = document.getElementById("html2blocks-form");
    const htmlBox = document.getElementById("html2blocks-fragment");
    const blocksBox = document.getElementById("html2blocks-blocks");
    const blocksStatus = document.getElementById("html2blocks-blocks-status");
    const debugBox = document.getElementById("html2blocks-debug");
    const copyHtmlBtn = document.getElementById("html2blocks-copy-html");
    const copyBlocksBtn = document.getElementById("html2blocks-copy-blocks");
    const useAiCheckbox = document.getElementById("h2b-use-ai");

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    const createError = (message, debug) => {
        const error = new Error(message || "Request failed");
        error.debug = debug;
        return error;
    };

    const setDebug = (details) => {
        if (!debugBox) {
            return;
        }

        if (!details) {
            debugBox.textContent = "";
            debugBox.style.display = "none";
            return;
        }

        debugBox.textContent = typeof details === "string" ? details : JSON.stringify(details, null, 2);
        debugBox.style.display = "block";
    };

    const parseErrorFromResponse = async (res) => {
        let raw = "";

        try {
            raw = await res.text();
        } catch (e) {
            return createError("Request failed", {
                status: res.status,
                statusText: res.statusText,
                reason: "Unable to read response body",
            });
        }

        try {
            const payload = JSON.parse(raw);
            const message = payload && payload.message ? payload.message : (raw || "Request failed");

            return createError(message, {
                status: res.status,
                statusText: res.statusText,
                payload,
            });
        } catch (e) {
            if (raw) {
                return createError(raw, {
                    status: res.status,
                    statusText: res.statusText,
                    raw,
                });
            }
        }

        return createError("Request failed", {
            status: res.status,
            statusText: res.statusText,
        });
    };

    const runAiBatch = async ({ url, language, selector }) => {
        const startRes = await fetch(HTML2BLOCKS_DATA.aiBatchStart, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": HTML2BLOCKS_DATA.nonce,
            },
            body: JSON.stringify({ url, language, selector }),
        });

        if (!startRes.ok) {
            throw await parseErrorFromResponse(startRes);
        }

        const startData = await startRes.json();
        const batchId = startData.batchId;
        if (!batchId) {
            throw new Error("AI batch start did not return a batch id.");
        }

        return {
            html: startData.html || "",
            totalChunks: Number(startData.totalChunks || 0),
            poll: async () => {
                const params = new URLSearchParams({ batchId });
                const statusRes = await fetch(HTML2BLOCKS_DATA.aiBatchStatus + "?" + params.toString(), {
                    headers: { "X-WP-Nonce": HTML2BLOCKS_DATA.nonce },
                });

                if (!statusRes.ok) {
                    throw await parseErrorFromResponse(statusRes);
                }

                return statusRes.json();
            },
        };
    };

    const updateCopyState = () => {
        copyBlocksBtn.disabled = !blocksBox.value;
    };

    updateCopyState();

    if (useAiCheckbox && !HTML2BLOCKS_DATA.aiAvailable) {
        useAiCheckbox.disabled = true;
        useAiCheckbox.title = "WP AI Client is not available on this site.";
    }

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        const useAi = !!(useAiCheckbox && useAiCheckbox.checked);

        htmlBox.textContent = useAi ? "Fetching HTML and generating blocks with AI..." : "Fetching...";
        blocksBox.value = "";
        blocksStatus.textContent = useAi ? "Using WP AI Client." : "Using local block converter.";
        setDebug("");
        updateCopyState();

        const url = document.getElementById("h2b-url").value.trim();
        const language = document.getElementById("h2b-language").value.trim();
        const selector = document.getElementById("h2b-selector").value.trim() || "body";

        const params = new URLSearchParams({ url, selector });
        if (language) params.append("language", language);
        if (useAi) params.append("use_ai", "1");

        try {
            if (useAi) {
                const aiBatch = await runAiBatch({ url, language, selector });
                htmlBox.textContent = "";
                htmlBox.innerHTML = aiBatch.html;

                let status;
                const total = aiBatch.totalChunks;

                do {
                    status = await aiBatch.poll();

                    const completed = Number(status.completedChunks || 0);
                    const totalChunks = Number(status.totalChunks || total || 0);

                    if (status.status === "failed") {
                        throw createError(status.error || "AI batch conversion failed.", {
                            batchStatus: status,
                        });
                    }

                    if (totalChunks > 0) {
                        blocksStatus.textContent = "Converting with WP AI Client: chunk " + completed + " of " + totalChunks + ".";
                    } else {
                        blocksStatus.textContent = "Converting with WP AI Client...";
                    }

                    if (status.status !== "completed") {
                        await sleep(1000);
                    }
                } while (status.status !== "completed");

                blocksBox.value = status.blocks || "";
                blocksStatus.textContent = blocksBox.value
                    ? "Blocks ready to copy. Generated with WP AI Client in batches."
                    : "No block output was generated.";
                setDebug("");
                updateCopyState();

                copyHtmlBtn.onclick = () => {
                    navigator.clipboard.writeText(aiBatch.html || "").then(() => {
                        copyHtmlBtn.textContent = "Copied!";
                        setTimeout(() => (copyHtmlBtn.textContent = "Copy HTML"), 1500);
                    });
                };

                copyBlocksBtn.onclick = () => {
                    if (!blocksBox.value) {
                        return;
                    }
                    navigator.clipboard.writeText(blocksBox.value).then(() => {
                        copyBlocksBtn.textContent = "Copied!";
                        setTimeout(() => (copyBlocksBtn.textContent = "Copy Blocks"), 1500);
                    });
                };

                return;
            }

            const res = await fetch(HTML2BLOCKS_DATA.rest + "?" + params.toString(), {
                headers: { "X-WP-Nonce": HTML2BLOCKS_DATA.nonce },
            });
            if (!res.ok) {
                throw await parseErrorFromResponse(res);
            }
            const data = await res.json();

            htmlBox.textContent = "";
            htmlBox.innerHTML = data.html || "";
            blocksBox.value = data.blocks || "";
            blocksStatus.textContent = data.blocksError
                ? data.blocksError
                : data.blocks
                    ? "Blocks ready to copy. Generated with " + (data.conversionMethod === "ai" ? "WP AI Client" : "the local converter") + "."
                    : "No block output was generated.";
            setDebug(data.blocksError ? data : "");
            updateCopyState();

            copyHtmlBtn.onclick = () => {
                navigator.clipboard.writeText(data.html || "").then(() => {
                    copyHtmlBtn.textContent = "Copied!";
                    setTimeout(() => (copyHtmlBtn.textContent = "Copy HTML"), 1500);
                });
            };

            copyBlocksBtn.onclick = () => {
                if (!data.blocks) {
                    return;
                }
                navigator.clipboard.writeText(data.blocks || "").then(() => {
                    copyBlocksBtn.textContent = "Copied!";
                    setTimeout(() => (copyBlocksBtn.textContent = "Copy Blocks"), 1500);
                });
            };
        } catch (err) {
            htmlBox.textContent = "Error: " + err.message;
            blocksBox.value = "";
            blocksStatus.textContent = "Error: " + (err.message || "Request failed");
            setDebug(err.debug || err.stack || String(err));
            updateCopyState();
        }
    });
})();
