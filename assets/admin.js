(function(){
    const form = document.getElementById('html2blocks-form');
    const resultBox = document.getElementById('html2blocks-fragment');
    const copyBtn = document.getElementById('html2blocks-copy');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        resultBox.textContent = 'Fetching...';

        const url = document.getElementById('h2b-url').value.trim();
        const language = document.getElementById('h2b-language').value.trim();
        const selector = document.getElementById('h2b-selector').value.trim() || 'body';

        const params = new URLSearchParams({ url, selector });
        if (language) params.append('language', language);

        try {
            const res = await fetch(HTML2BLOCKS_DATA.rest + '?' + params.toString(), {
                headers: { 'X-WP-Nonce': HTML2BLOCKS_DATA.nonce }
            });
            if (!res.ok) {
                const t = await res.text();
                throw new Error(t || 'Request failed');
            }
            const data = await res.json();
            resultBox.textContent = '';
            resultBox.innerText = '';
            resultBox.innerHTML = data.html;
            copyBtn.onclick = () => {
                navigator.clipboard.writeText(data.html).then(() => {
                    copyBtn.textContent = 'Copied!';
                    setTimeout(()=>copyBtn.textContent='Copy HTML',1500);
                });
            };
        } catch (err) {
            resultBox.textContent = 'Error: ' + err.message;
        }
    });
})();