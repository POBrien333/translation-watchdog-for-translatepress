(function () {
    const data = window.trwatchData;
    const btn = document.getElementById('trwatch-refresh'), prog = document.getElementById('trwatch-progress');
    if (!data || !btn || btn.disabled) return;
    const {ajax, nonce, batch, i18n} = data;
    const bar = prog.querySelector('progress'), label = prog.querySelector('span');
    const results = document.getElementById('trwatch-results');
    const fmt = (s, ...args) => args.reduce((out, a, i) => out.replace(`%${i + 1}$d`, a), s).replace('%d', args[0]);
    let currentScan = '';

    // always returns parsed JSON; turns WordPress' bare "-1"/"0" replies into readable errors
    async function post(action, extra = {}) {
        const body = extra instanceof URLSearchParams ? extra : new URLSearchParams(extra);
        body.set('action', action); body.set('_ajax_nonce', nonce);
        const r = await fetch(ajax, {method: 'POST', body, credentials: 'same-origin'});
        const text = await r.text();
        try { return JSON.parse(text); }
        catch (e) {
            throw new Error(text.trim() === '-1' || r.status === 403 ? i18n.expired : fmt(i18n.unexpected, r.status));
        }
    }
    const fail = r => { throw new Error((r && r.data) || i18n.failed); };

    async function scan() {
        btn.disabled = true; prog.hidden = false; bar.value = 0; label.textContent = i18n.collecting;
        try {
            const start = await post('trwatch_start');
            if (!start.success) fail(start);
            currentScan = start.data.scan;
            // offset counts original pages; shown counts translated pages (originals × languages)
            const total = start.data.total; let offset = 0, shown = 0, found = 0, first = true;
            while (true) {
                label.textContent = fmt(i18n.checking, Math.min(shown, total), total);
                const r = await post('trwatch_batch', {scan: currentScan, offset});
                if (!r.success) fail(r);
                if (r.data.done) { results.innerHTML = r.data.html; break; }
                if (first) {   // keep old results visible until the first batch is in
                    results.innerHTML = '';
                    const meta = document.createElement('p');
                    meta.className = 'trwatch-meta';
                    meta.append(i18n.scanning + ' ');
                    const strong = document.createElement('strong');
                    strong.className = 'trwatch-total'; strong.textContent = '0';
                    meta.append(strong, ' ' + i18n.soFar);
                    const live = document.createElement('div'); live.id = 'trwatch-live';
                    results.append(meta, live);
                    first = false;
                }
                found += r.data.found;
                results.querySelector('.trwatch-total').textContent = found;
                document.getElementById('trwatch-live').insertAdjacentHTML('beforeend', r.data.cards);
                offset = r.data.next; shown = r.data.shown; bar.value = shown / total * 100;
            }
            prog.hidden = true;
        } catch (e) { label.textContent = i18n.scanFailed + ' ' + e.message; }
        currentScan = '';
        btn.disabled = false;
    }

    function refreshCounts() {
        let total = 0;
        results.querySelectorAll('.trwatch-page:not(.trwatch-err)').forEach(card => {
            const n = card.querySelectorAll('li').length;
            card.hidden = !n;   // hidden, not removed, so a failed skip can put rows back
            const badge = card.querySelector('.trwatch-n'); if (badge) badge.textContent = n;
            total += n;
        });
        const t = results.querySelector('.trwatch-total'); if (t) t.textContent = total;
    }

    function showSkipped(d) {
        document.getElementById('trwatch-skipped').innerHTML = d.html;
        document.getElementById('trwatch-skipcount').textContent = d.count;
    }

    async function skip(hash) {
        const removed = [];
        results.querySelectorAll('li[data-hash]').forEach(li => {
            if (li.dataset.hash !== hash) return;
            removed.push([li, li.parentNode, li.nextSibling]);
            li.remove();
        });
        refreshCounts();
        try {
            const r = await post('trwatch_skip', {hash, scan: currentScan, undo: '0'});
            if (!r.success) fail(r);
            showSkipped(r.data);
        } catch (e) {
            removed.forEach(([li, parent, next]) => parent.insertBefore(li, next));
            refreshCounts();
            alert(i18n.skipFailed + ' ' + e.message);
        }
    }

    async function unskip(hash) {
        try {
            const r = await post('trwatch_skip', {hash, undo: '1'});
            if (!r.success) fail(r);
            showSkipped(r.data);   // the string shows again on the next scan
        } catch (e) { alert(i18n.undoFailed + ' ' + e.message); }
    }

    async function retry(rbtn) {
        const urls = [...results.querySelectorAll('.trwatch-failed li[data-url]')].map(li => li.dataset.url);
        btn.disabled = rbtn.disabled = true; prog.hidden = false; bar.value = 0;
        try {
            for (let i = 0; i < urls.length; i += batch) {
                label.textContent = fmt(i18n.retrying, i, urls.length);
                const body = new URLSearchParams();
                urls.slice(i, i + batch).forEach(u => body.append('urls[]', u));
                const r = await post('trwatch_retry', body);
                if (!r.success) fail(r);
                bar.value = (i + batch) / urls.length * 100;
            }
            const r = await post('trwatch_results');
            if (!r.success) fail(r);
            results.innerHTML = r.data.html;
            prog.hidden = true;
        } catch (e) { label.textContent = i18n.retryFailed + ' ' + e.message; rbtn.disabled = false; }
        btn.disabled = false;
    }

    document.addEventListener('click', e => {
        if (e.target.id === 'trwatch-retry') return retry(e.target);
        const s = e.target.closest('.trwatch-skip'), u = e.target.closest('.trwatch-unskip');
        if (s) skip(s.closest('li').dataset.hash);
        if (u) unskip(u.closest('li').dataset.hash);
    });

    btn.addEventListener('click', scan);
    scan();   // scan on screen load
})();
