/**
 * CineStickers - shared sticker picker + in-chat sticker studio
 * Usage: CineStickers.attach({ btn, picker, onSelect: (fileName) => {...} })
 */
(function () {
    const SITE = window.SITE_URL || '';
    let cache = null;
    let canCreate = false;

    function isVideo(f) { return /\.(webm|mp4)$/i.test(f); }

    function mediaTag(file, size) {
        if (isVideo(file)) {
            return `<video src="${SITE}/uploads/stickers/${file}" autoplay loop muted playsinline style="width:${size}px;height:${size}px;object-fit:contain;"></video>`;
        }
        return `<img src="${SITE}/uploads/stickers/${file}" alt="" loading="lazy" style="width:${size}px;height:${size}px;object-fit:contain;">`;
    }

    function load(force) {
        if (cache && !force) return Promise.resolve(cache);
        return fetch(`${SITE}/api/sticker.php?action=list`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(d => {
                cache = (d && d.stickers) ? d.stickers : [];
                canCreate = !!(d && d.can_create);
                return cache;
            })
            .catch(() => (cache = []));
    }

    function render(picker, onSelect) {
        const state = picker._cs || (picker._cs = { tab: 'all' });
        const list = (cache || []).filter(s => state.tab === 'all' ? true : (state.tab === 'mine' ? s.is_mine : !s.is_mine));

        const tabs = `
            <div class="cs-tabs">
                <button type="button" class="cs-tab ${state.tab === 'all' ? 'active' : ''}" data-cs-tab="all">هەموو</button>
                <button type="button" class="cs-tab ${state.tab === 'global' ? 'active' : ''}" data-cs-tab="global">گشتی</button>
                <button type="button" class="cs-tab ${state.tab === 'mine' ? 'active' : ''}" data-cs-tab="mine">هی من</button>
                ${canCreate ? '<button type="button" class="cs-create-btn" data-cs-create="1"><i class="fas fa-plus"></i> ستیکەری نوێ</button>' : ''}
            </div>`;

        const items = list.length ? list.map(s => `
            <div class="cs-item" data-cs-file="${s.file_name}" title="${(s.name || '').replace(/"/g, '')}">
                ${mediaTag(s.file_name, 62)}
                ${s.is_mine ? `<button type="button" class="cs-del" data-cs-del="${s.id}" title="سڕینەوە">&times;</button>` : ''}
            </div>`).join('')
            : `<div class="cs-empty">
                    <i class="fas fa-face-smile"></i>
                    <p>هیچ ستیکەرێک نییە${canCreate ? ' — ستیکەری خۆت دروست بکە!' : ''}</p>
               </div>`;

        picker.innerHTML = `${tabs}<div class="cs-grid">${items}</div>`;

        picker.querySelectorAll('[data-cs-tab]').forEach(b => b.addEventListener('click', e => {
            e.stopPropagation();
            state.tab = b.dataset.csTab;
            render(picker, onSelect);
        }));
        const createBtn = picker.querySelector('[data-cs-create]');
        if (createBtn) createBtn.addEventListener('click', e => { e.stopPropagation(); openStudio(picker, onSelect); });

        picker.querySelectorAll('.cs-item').forEach(el => el.addEventListener('click', e => {
            if (e.target.closest('[data-cs-del]')) return;
            onSelect(el.dataset.csFile);
        }));
        picker.querySelectorAll('[data-cs-del]').forEach(btn => btn.addEventListener('click', e => {
            e.stopPropagation();
            if (!confirm('ستیکەرەکە بسڕدرێتەوە؟')) return;
            fetch(`${SITE}/api/sticker.php?action=delete`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
                body: JSON.stringify({ id: btn.dataset.csDel })
            }).then(r => r.json()).then(() => load(true).then(() => render(picker, onSelect)));
        }));
    }

    // ---------- Sticker studio (create sticker from image / gif / video) ----------
    function ensureStudio() {
        let modal = document.getElementById('cs-studio');
        if (modal) return modal;
        modal = document.createElement('div');
        modal.id = 'cs-studio';
        modal.className = 'cs-studio';
        modal.innerHTML = `
            <div class="cs-studio-card glass-strong">
                <div class="cs-studio-head">
                    <h3><i class="fas fa-wand-magic-sparkles"></i> دروستکردنی ستیکەر</h3>
                    <button type="button" class="cs-studio-close">&times;</button>
                </div>
                <label class="cs-drop" id="cs-drop">
                    <input type="file" id="cs-file" accept="image/png,image/jpeg,image/gif,image/webp,video/webm,video/mp4" hidden>
                    <div class="cs-drop-inner" id="cs-drop-inner">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <span>وێنە، GIF یان ڤیدیۆ هەڵبژێرە</span>
                        <small>PNG · JPG · GIF · WebP · WebM · MP4 — حەدی ٥MB</small>
                    </div>
                </label>
                <input type="text" id="cs-name" class="cs-name" maxlength="40" placeholder="ناوی ستیکەر (ئارەزوومەندانە)">
                <div class="cs-studio-msg" id="cs-msg"></div>
                <button type="button" class="cs-save" id="cs-save" disabled><i class="fas fa-check"></i> زیادکردن بۆ ستیکەرەکانم</button>
            </div>`;
        document.body.appendChild(modal);
        return modal;
    }

    function openStudio(picker, onSelect) {
        const modal = ensureStudio();
        const fileInput = modal.querySelector('#cs-file');
        const dropInner = modal.querySelector('#cs-drop-inner');
        const saveBtn = modal.querySelector('#cs-save');
        const msg = modal.querySelector('#cs-msg');
        const nameInput = modal.querySelector('#cs-name');

        fileInput.value = ''; nameInput.value = ''; msg.textContent = ''; saveBtn.disabled = true;
        dropInner.innerHTML = `<i class="fas fa-cloud-arrow-up"></i><span>وێنە، GIF یان ڤیدیۆ هەڵبژێرە</span><small>PNG · JPG · GIF · WebP · WebM · MP4 — حەدی ٥MB</small>`;
        modal.classList.add('open');

        modal.querySelector('.cs-studio-close').onclick = () => modal.classList.remove('open');
        modal.onclick = e => { if (e.target === modal) modal.classList.remove('open'); };

        fileInput.onchange = () => {
            const f = fileInput.files[0];
            if (!f) return;
            if (f.size > 5 * 1024 * 1024) { msg.textContent = 'قەبارە زۆر گەورەیە (حەدی ٥MB)'; msg.className = 'cs-studio-msg err'; return; }
            const url = URL.createObjectURL(f);
            dropInner.innerHTML = f.type.startsWith('video')
                ? `<video src="${url}" autoplay loop muted playsinline class="cs-preview"></video>`
                : `<img src="${url}" class="cs-preview">`;
            msg.textContent = ''; saveBtn.disabled = false;
        };

        saveBtn.onclick = () => {
            const f = fileInput.files[0];
            if (!f) return;
            const fd = new FormData();
            fd.append('sticker_file', f);
            fd.append('name', nameInput.value.trim() || 'ستیکەر');
            fd.append('scope', 'personal');
            saveBtn.disabled = true;
            msg.className = 'cs-studio-msg';
            msg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> بارکردن...';
            fetch(`${SITE}/api/sticker.php?action=upload`, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        msg.className = 'cs-studio-msg ok';
                        msg.innerHTML = '<i class="fas fa-check"></i> ستیکەرەکە زیادکرا!';
                        load(true).then(() => {
                            if (picker) { picker._cs = { tab: 'mine' }; render(picker, onSelect); }
                            setTimeout(() => modal.classList.remove('open'), 700);
                        });
                    } else {
                        msg.className = 'cs-studio-msg err';
                        msg.textContent = d.message || 'هەڵەیەک ڕوویدا';
                        saveBtn.disabled = false;
                    }
                }).catch(() => {
                    msg.className = 'cs-studio-msg err';
                    msg.textContent = 'هەڵەی پەیوەندی';
                    saveBtn.disabled = false;
                });
        };
    }

    function attach(opts) {
        const btn = typeof opts.btn === 'string' ? document.getElementById(opts.btn) : opts.btn;
        const picker = typeof opts.picker === 'string' ? document.getElementById(opts.picker) : opts.picker;
        if (!btn || !picker) return;
        picker.classList.add('cs-picker');

        btn.addEventListener('click', e => {
            e.stopPropagation();
            const open = picker.classList.toggle('open');
            picker.style.display = open ? 'block' : 'none';
            if (open) load().then(() => render(picker, opts.onSelect));
        });
        document.addEventListener('click', e => {
            if (picker.classList.contains('open') && !e.target.closest('.cs-picker') && !e.target.closest('#cs-studio') && e.target !== btn && !btn.contains(e.target)) {
                picker.classList.remove('open');
                picker.style.display = 'none';
            }
        });
    }

    window.CineStickers = { attach, load, render, isVideo, mediaTag, close: (p) => { p.classList.remove('open'); p.style.display = 'none'; } };
})();
