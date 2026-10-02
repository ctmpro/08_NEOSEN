/* ==========================================================================
   NEOSEN — Back-office (vanilla JS)
   ========================================================================== */
(() => {
    'use strict';
    const $ = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
    const csrf = () => ($('input[name="_csrf"]') || {}).value || '';
    const base = (document.querySelector('link[href*="/assets/css/admin.css"]')?.getAttribute('href') || '').split('/assets/')[0];

    /* ---------- Menu latéral (mobile) ---------- */
    const sidebar = $('[data-sidebar]');
    $('[data-sidebar-toggle]')?.addEventListener('click', () => sidebar.classList.toggle('is-open'));
    $('[data-sidebar-close]')?.addEventListener('click', () => sidebar.classList.remove('is-open'));

    /* ---------- Alertes ---------- */
    $$('.alert-close').forEach(b => b.addEventListener('click', () => b.parentElement.remove()));
    setTimeout(() => $$('.alert-success').forEach(a => { a.style.transition = 'opacity .5s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 500); }), 5000);

    /* ---------- Confirmation des suppressions ---------- */
    $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

    /* ---------- Affichage du mot de passe ---------- */
    $$('[data-pw-toggle]').forEach(btn => btn.addEventListener('click', () => {
        const input = btn.parentElement.querySelector('input');
        input.type = input.type === 'password' ? 'text' : 'password';
    }));

    /* ---------- Slug automatique ---------- */
    const slugify = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    $$('[data-slug-source]').forEach(src => {
        const target = src.form.querySelector('[data-slug-target]');
        if (!target) return;
        let manual = target.value !== '';
        target.addEventListener('input', () => { manual = target.value !== ''; });
        src.addEventListener('input', () => { if (!manual) target.value = slugify(src.value); });
    });

    /* ---------- Couleurs ---------- */
    $$('[data-color-sync]').forEach(picker => {
        const text = document.getElementById(picker.dataset.colorSync);
        picker.addEventListener('input', () => { text.value = picker.value; });
        text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
    });

    /* ---------- Aperçu des médias ---------- */
    $$('[data-media-input]').forEach(input => input.addEventListener('change', () => {
        const file = input.files[0];
        const preview = input.closest('.media-input').querySelector('[data-media-preview]');
        if (!file) return;
        const url = URL.createObjectURL(file);
        preview.classList.remove('is-empty');
        preview.innerHTML = file.type.startsWith('video') ? `<video src="${url}" muted controls></video>` : `<img src="${url}" alt="">`;
    }));

    /* ---------- Zone de dépôt multiple ---------- */
    $$('[data-dropzone]').forEach(zone => {
        const input = zone.querySelector('input[type=file]');
        const out = zone.querySelector('[data-dropzone-files]');
        input.addEventListener('change', () => { out.textContent = input.files.length ? `${input.files.length} fichier(s) prêt(s) — enregistrez pour les ajouter` : ''; });
        ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, () => zone.classList.add('is-drag')));
        ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('is-drag')));
    });

    /* ---------- Suggestions de technologies ---------- */
    $$('[data-tech-input]').forEach(input => {
        const box = input.parentElement.querySelector('[data-tech-suggest]');
        const list = () => input.value.split(',').map(s => s.trim()).filter(Boolean);
        const refresh = () => $$('.chip-btn', box).forEach(b => b.classList.toggle('is-used', list().map(x => x.toLowerCase()).includes(b.dataset.value.toLowerCase())));
        box.addEventListener('click', e => {
            const b = e.target.closest('.chip-btn');
            if (!b) return;
            input.value = [...list(), b.dataset.value].join(', ');
            input.dispatchEvent(new Event('input', { bubbles: true }));
            refresh();
        });
        input.addEventListener('input', refresh);
        refresh();
    });

    /* ---------- Mini éditeur HTML ---------- */
    $$('.rte-toolbar').forEach(bar => {
        const ta = document.getElementById(bar.dataset.rteFor);
        const preview = bar.nextElementSibling;
        bar.addEventListener('click', e => {
            const btn = e.target.closest('button');
            if (!btn) return;
            if (btn.hasAttribute('data-preview')) {
                const show = preview.hidden;
                preview.hidden = !show;
                ta.hidden = show;
                btn.classList.toggle('is-active', show);
                if (show) preview.innerHTML = ta.value.replace(/<script[\s\S]*?<\/script>/gi, '').replace(/\son\w+="[^"]*"/gi, '');
                return;
            }
            const tag = btn.dataset.tag;
            const [s, en] = [ta.selectionStart, ta.selectionEnd];
            const sel = ta.value.slice(s, en) || 'texte';
            let out;
            if (tag === 'a') {
                const href = prompt('Adresse du lien :', 'https://');
                if (!href) return;
                out = `<a href="${href}">${sel}</a>`;
            } else if (tag === 'ul') {
                out = '<ul>\n' + sel.split('\n').filter(Boolean).map(l => `  <li>${l.replace(/^[-•*]\s*/, '')}</li>`).join('\n') + '\n</ul>';
            } else {
                out = `<${tag}>${sel}</${tag}>`;
            }
            ta.setRangeText(out, s, en, 'end');
            ta.focus();
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    /* ---------- Avertissement en cas de modifications non enregistrées ---------- */
    $$('form[data-dirty-check]').forEach(form => {
        let dirty = false;
        form.addEventListener('input', () => { dirty = true; });
        form.addEventListener('change', () => { dirty = true; });
        form.addEventListener('submit', () => { dirty = false; });
        window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    });

    /* ---------- Réordonnancement par glisser-déposer (souris + tactile) ---------- */
    $$('[data-sortable]').forEach(list => {
        const table = list.dataset.sortable;
        let dragged = null;

        const save = () => {
            const ids = Array.from(list.children).map(el => el.dataset.id).filter(Boolean);
            fetch(`${base}/api/reorder.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'Accept': 'application/json' },
                body: JSON.stringify({ table, ids }),
            }).then(r => r.json()).then(d => {
                if (d.success) { list.classList.add('drop-saved'); setTimeout(() => list.classList.remove('drop-saved'), 1000); }
                else alert(d.message || 'Erreur lors de l\'enregistrement de l\'ordre.');
            }).catch(() => alert('Connexion impossible : ordre non enregistré.'));
        };

        list.addEventListener('pointerdown', e => {
            const handle = e.target.closest('.drag-handle');
            if (!handle || !list.contains(handle)) return;
            dragged = Array.from(list.children).find(c => c.contains(handle));
            if (!dragged) return;
            e.preventDefault();
            dragged.classList.add('is-dragging');
            document.body.style.userSelect = 'none';

            const move = ev => {
                const target = document.elementFromPoint(ev.clientX, ev.clientY);
                const over = target && Array.from(list.children).find(c => c !== dragged && c.contains(target));
                if (!over) return;
                const r = over.getBoundingClientRect();
                const horizontal = getComputedStyle(list).display === 'grid' && r.width < list.getBoundingClientRect().width / 1.5;
                const after = horizontal ? ev.clientX > r.left + r.width / 2 : ev.clientY > r.top + r.height / 2;
                list.insertBefore(dragged, after ? over.nextSibling : over);
            };
            const up = () => {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', up);
                document.body.style.userSelect = '';
                dragged.classList.remove('is-dragging');
                dragged = null;
                save();
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
        });
        $$('.drag-handle', list).forEach(h => { h.style.touchAction = 'none'; });
    });
})();
