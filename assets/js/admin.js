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

/* ==========================================================================
   Gestion : lignes de documents, pipeline, utilitaires
   ========================================================================== */
(() => {
    'use strict';
    const $ = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

    /* ---------- Lignes cliquables ---------- */
    $$('tr.row-link').forEach(tr => tr.addEventListener('click', e => {
        if (e.target.closest('a, button, input, select, form')) return;
        window.location = tr.dataset.href;
    }));

    /* ---------- Copier le lien client ---------- */
    $$('[data-copy]').forEach(btn => btn.addEventListener('click', () => {
        const input = btn.parentElement.querySelector('input');
        input.select();
        (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.resolve(document.execCommand('copy')))
            .then(() => { btn.textContent = 'Copié ✓'; setTimeout(() => { btn.textContent = 'Copier'; }, 1800); });
    }));

    /* ---------- Éditeur de lignes (devis / factures) ---------- */
    const editor = $('[data-lines-editor]');
    if (editor) {
        const form = editor.closest('form');
        const body = $('[data-lines]', editor);
        const rates = JSON.parse(editor.dataset.vatRates || '["20"]');
        const curSel = form.querySelector('[name="currency"]');
        const num = v => parseFloat(String(v || '').replace(/\s| /g, '').replace(',', '.')) || 0;
        const fmt = v => {
            const cur = curSel ? curSel.value : 'EUR';
            const d = cur === 'XOF' ? 0 : 2;
            const s = v.toLocaleString('fr-FR', { minimumFractionDigits: d, maximumFractionDigits: d });
            return cur === 'XOF' ? s + ' FCFA' : s + ' €';
        };
        const round = v => { const d = curSel && curSel.value === 'XOF' ? 0 : 2; return Math.round(v * 10 ** d) / 10 ** d; };

        const renumber = () => $$('[data-line]', body).forEach((line, i) => {
            $$('[name^="lines["]', line).forEach(el => { el.name = el.name.replace(/^lines\[\d+\]/, `lines[${i}]`); });
        });

        const recalc = () => {
            let sub = 0; const byRate = {};
            $$('[data-line]', body).forEach(line => {
                if (line.classList.contains('line--section')) return;
                const f = n => line.querySelector(`[data-f="${n}"]`);
                const total = round(num(f('quantity').value) * num(f('unit_price').value) * (1 - num(f('discount_pct').value) / 100));
                line.querySelector('[data-total]').textContent = fmt(total);
                sub += total;
                const r = num(f('vat_rate').value);
                byRate[r] = (byRate[r] || 0) + total;
            });
            const disc = num($('[data-discount]', editor).value);
            let tax = 0;
            Object.entries(byRate).forEach(([r, base]) => { tax += round(base * (1 - disc / 100) * r / 100); });
            const discount = round(sub * disc / 100);
            $('[data-t="subtotal"]', editor).textContent = fmt(sub);
            $('[data-t="discount"]', editor).textContent = '− ' + fmt(discount);
            $('[data-row-discount]', editor).hidden = disc <= 0;
            $('[data-t="tax"]', editor).textContent = fmt(tax);
            $('[data-t="total"]', editor).textContent = fmt(round(sub - discount + tax));
        };

        const vatOptions = sel => rates.map(r => `<option value="${r}"${String(r) === String(sel) ? ' selected' : ''}>${String(r).replace('.', ',')}</option>`).join('');
        const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const trash = editor.querySelector('[data-remove]')?.innerHTML || '×';
        const addLine = (data = {}, section = false) => {
            const div = document.createElement('div');
            div.className = 'line' + (section ? ' line--section' : '');
            div.setAttribute('data-line', '');
            div.innerHTML = `<span class="line-move"><button type="button" class="icon-btn" data-up title="Monter">↑</button><button type="button" class="icon-btn" data-down title="Descendre">↓</button></span>
                <input type="hidden" name="lines[0][line_type]" value="${section ? 'section' : 'item'}" data-f="line_type">
                <span class="line-desc"><input type="text" name="lines[0][description]" value="${esc(data.description)}" placeholder="${section ? 'Titre de section' : 'Désignation'}" data-f="description" maxlength="255">
                ${section ? '' : `<textarea name="lines[0][details]" rows="1" placeholder="Détails (optionnel)" data-f="details">${esc(data.details)}</textarea>`}</span>
                ${section ? '<span class="line-section-fill"></span>' : `
                <input type="text" inputmode="decimal" name="lines[0][quantity]" value="${esc(data.quantity ?? 1)}" data-f="quantity" aria-label="Quantité">
                <input type="text" name="lines[0][unit]" value="${esc(data.unit ?? 'forfait')}" data-f="unit" aria-label="Unité" list="units">
                <input type="text" inputmode="decimal" name="lines[0][unit_price]" value="${esc(data.unit_price ?? '')}" data-f="unit_price" aria-label="Prix unitaire HT">
                <select name="lines[0][vat_rate]" data-f="vat_rate" aria-label="TVA">${vatOptions(data.vat_rate ?? rates[0])}</select>
                <input type="text" inputmode="decimal" name="lines[0][discount_pct]" value="" data-f="discount_pct" aria-label="Remise %" placeholder="0">
                <span class="line-total num" data-total>—</span>`}
                <button type="button" class="icon-btn icon-btn--danger" data-remove title="Supprimer la ligne">${trash}</button>`;
            body.appendChild(div);
            renumber(); recalc();
            div.querySelector('[data-f="description"]').focus();
            form.dispatchEvent(new Event('input'));
        };

        editor.addEventListener('input', recalc);
        editor.addEventListener('change', recalc);
        curSel && curSel.addEventListener('change', recalc);
        $('[data-add-line]', editor).addEventListener('click', () => addLine({ vat_rate: rates.includes(String(form.dataset.defaultVat)) ? form.dataset.defaultVat : rates[0] }));
        $('[data-add-section]', editor).addEventListener('click', () => addLine({}, true));
        body.addEventListener('click', e => {
            const line = e.target.closest('[data-line]');
            if (!line) return;
            if (e.target.closest('[data-remove]')) { line.remove(); renumber(); recalc(); form.dispatchEvent(new Event('input')); }
            if (e.target.closest('[data-up]') && line.previousElementSibling) { body.insertBefore(line, line.previousElementSibling); renumber(); }
            if (e.target.closest('[data-down]') && line.nextElementSibling) { body.insertBefore(line.nextElementSibling, line); renumber(); }
        });
        const cat = $('[data-catalog]');
        cat && cat.addEventListener('change', () => {
            const opt = cat.selectedOptions[0];
            if (opt && opt.dataset.item) {
                // Remplace la première ligne si elle est vide
                const first = $('[data-line]', body);
                if (first && $$('[data-line]', body).length === 1 && !first.querySelector('[data-f="description"]').value) first.remove();
                addLine(JSON.parse(opt.dataset.item));
            }
            cat.value = '';
        });
        // Devise du client choisie automatiquement
        const clientSel = form.querySelector('[data-client-select]');
        clientSel && clientSel.addEventListener('change', () => {
            const c = clientSel.selectedOptions[0]?.dataset.currency;
            if (c && curSel) { curSel.value = c; recalc(); }
        });
        recalc();
    }

    /* ---------- Pipeline : glisser-déposer des cartes ---------- */
    const kanban = $('[data-kanban]');
    if (kanban) {
        let dragged = null;
        const token = ($('input[name="_csrf"]') || {}).value || '';
        $$('.kanban-card', kanban).forEach(card => {
            card.addEventListener('dragstart', e => { dragged = card; card.classList.add('is-dragging'); e.dataTransfer.effectAllowed = 'move'; });
            card.addEventListener('dragend', () => { card.classList.remove('is-dragging'); dragged = null; });
        });
        $$('[data-kanban-list]', kanban).forEach(list => {
            list.addEventListener('dragover', e => { e.preventDefault(); list.classList.add('is-over'); });
            list.addEventListener('dragleave', () => list.classList.remove('is-over'));
            list.addEventListener('drop', e => {
                e.preventDefault();
                list.classList.remove('is-over');
                if (!dragged) return;
                const stage = list.closest('[data-stage]').dataset.stage;
                list.prepend(dragged);
                const sel = dragged.querySelector('select[name="stage"]');
                if (sel) sel.value = stage;
                const fd = new FormData();
                fd.append('_csrf', token); fd.append('action', 'stage'); fd.append('id', dragged.dataset.id); fd.append('stage', stage);
                fetch(window.location.pathname, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json()).catch(() => alert('Connexion impossible : étape non enregistrée.'));
            });
        });
    }
})();

/* Avertissements client (SIREN, TVA, adresse) dans l'éditeur de document */
(() => {
    const sel = document.querySelector('[data-client-select]');
    const box = document.querySelector('[data-client-warnings]');
    if (!sel || !box) return;
    const show = () => {
        let list = [];
        try { list = JSON.parse(sel.selectedOptions[0]?.dataset.warnings || '[]'); } catch (e) {}
        box.hidden = !list.length;
        box.innerHTML = list.length ? '<strong>À compléter dans la fiche client :</strong><ul class="warn-list">' + list.map(w => `<li>${w.replace(/</g, '&lt;')}</li>`).join('') + `</ul><a class="link" target="_blank" href="client-edit.php?id=${sel.value}">Compléter la fiche</a>` : '';
    };
    sel.addEventListener('change', show);
    show();
})();
