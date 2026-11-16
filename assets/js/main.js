/* ==========================================================================
   NEOSEN — Interactions (vanilla JS, aucune dépendance)
   ========================================================================== */
(() => {
    'use strict';

    const $ = (s, c = document) => c.querySelector(s);
    const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isSmall = window.matchMedia('(max-width: 599px)').matches;

    /* ---------- Header sticky ---------- */
    const header = $('[data-header]');
    const onScroll = () => header && header.classList.toggle('is-scrolled', window.scrollY > 12);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    /* ---------- Menu mobile ---------- */
    const toggle = $('[data-nav-toggle]');
    const mobileNav = $('[data-mobile-nav]');
    const setNav = (open) => {
        document.body.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        mobileNav.setAttribute('aria-hidden', String(!open));
    };
    if (toggle && mobileNav) {
        toggle.addEventListener('click', () => setNav(!document.body.classList.contains('nav-open')));
        $$('a', mobileNav).forEach(a => a.addEventListener('click', () => setNav(false)));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') setNav(false); });
        window.addEventListener('resize', () => { if (window.innerWidth >= 1080) setNav(false); });
    }

    /* ---------- Apparition au scroll ---------- */
    const revealEls = $$('[data-reveal]');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        revealEls.forEach(el => io.observe(el));
    } else {
        revealEls.forEach(el => el.classList.add('is-visible'));
    }

    /* ---------- Progression de la timeline ---------- */
    $$('[data-timeline]').forEach(tl => {
        const update = () => {
            const r = tl.getBoundingClientRect();
            const vh = window.innerHeight;
            const p = Math.min(1, Math.max(0, (vh * 0.75 - r.top) / r.height));
            tl.style.setProperty('--progress', p.toFixed(3));
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
    });

    /* ---------- Halo lumineux sur les cartes expertises ---------- */
    if (!isSmall) {
        $$('.expertise-card').forEach(card => {
            card.addEventListener('pointermove', e => {
                const r = card.getBoundingClientRect();
                card.style.setProperty('--mx', `${e.clientX - r.left}px`);
                card.style.setProperty('--my', `${e.clientY - r.top}px`);
            });
        });
    }

    /* ---------- Filtres des réalisations ---------- */
    $$('[data-filters]').forEach(group => {
        const grid = group.parentElement.querySelector('[data-projects-grid]');
        if (!grid) return;
        group.addEventListener('click', e => {
            const btn = e.target.closest('[data-filter]');
            if (!btn) return;
            $$('[data-filter]', group).forEach(b => {
                b.classList.toggle('is-active', b === btn);
                b.setAttribute('aria-selected', String(b === btn));
            });
            const f = btn.dataset.filter;
            $$('.project-card', grid).forEach(card => {
                const show = f === '*' || card.dataset.category === f;
                if (show) {
                    card.classList.remove('is-hidden');
                    card.classList.remove('is-visible');
                    requestAnimationFrame(() => requestAnimationFrame(() => card.classList.add('is-visible')));
                } else {
                    card.classList.add('is-hidden');
                }
            });
        });
    });

    /* ---------- Compteurs des chiffres clés ---------- */
    const counters = $$('[data-count]');
    if (counters.length && 'IntersectionObserver' in window && !reduceMotion) {
        const cio = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                cio.unobserve(el);
                const raw = el.dataset.count;
                const m = raw.match(/^(\D*)(\d+(?:[.,]\d+)?)(.*)$/);
                if (!m) return;
                const target = parseFloat(m[2].replace(',', '.'));
                const decimals = (m[2].split(/[.,]/)[1] || '').length;
                const start = performance.now();
                const dur = 1400;
                const tick = now => {
                    const t = Math.min(1, (now - start) / dur);
                    const eased = 1 - Math.pow(1 - t, 3);
                    el.textContent = m[1] + (target * eased).toFixed(decimals).replace('.', m[2].includes(',') ? ',' : '.') + m[3];
                    if (t < 1) requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
            });
        }, { threshold: 0.6 });
        counters.forEach(c => cio.observe(c));
    }

    /* ---------- Réseau animé du hero (canvas léger) ---------- */
    const canvas = $('[data-network]');
    if (canvas && !reduceMotion) {
        const ctx = canvas.getContext('2d');
        const styles = getComputedStyle(document.documentElement);
        const primary = styles.getPropertyValue('--primary').trim() || '#3B4BFF';
        let w, h, dpr, nodes = [], running = true, raf;
        const count = isSmall ? 22 : 46;
        const resize = () => {
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            w = canvas.offsetWidth; h = canvas.offsetHeight;
            canvas.width = w * dpr; canvas.height = h * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        };
        const init = () => {
            nodes = Array.from({ length: count }, () => ({
                x: Math.random() * w, y: Math.random() * h,
                vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25,
                r: Math.random() * 1.6 + .6,
            }));
        };
        const draw = () => {
            ctx.clearRect(0, 0, w, h);
            const max = isSmall ? 90 : 130;
            for (let i = 0; i < nodes.length; i++) {
                const a = nodes[i];
                a.x += a.vx; a.y += a.vy;
                if (a.x < 0 || a.x > w) a.vx *= -1;
                if (a.y < 0 || a.y > h) a.vy *= -1;
                for (let j = i + 1; j < nodes.length; j++) {
                    const b = nodes[j];
                    const dx = a.x - b.x, dy = a.y - b.y;
                    const d = Math.sqrt(dx * dx + dy * dy);
                    if (d < max) {
                        ctx.globalAlpha = (1 - d / max) * .35;
                        ctx.strokeStyle = primary;
                        ctx.lineWidth = .7;
                        ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
                    }
                }
                ctx.globalAlpha = .55;
                ctx.fillStyle = primary;
                ctx.beginPath(); ctx.arc(a.x, a.y, a.r, 0, Math.PI * 2); ctx.fill();
            }
            ctx.globalAlpha = 1;
            if (running) raf = requestAnimationFrame(draw);
        };
        resize(); init(); draw();
        let rt;
        window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(() => { resize(); init(); }, 200); });
        // Pause lorsque le hero n'est plus visible (économie de batterie)
        new IntersectionObserver(([e]) => {
            running = e.isIntersecting;
            cancelAnimationFrame(raf);
            if (running) raf = requestAnimationFrame(draw);
        }).observe(canvas);
        document.addEventListener('visibilitychange', () => {
            running = !document.hidden;
            cancelAnimationFrame(raf);
            if (running) raf = requestAnimationFrame(draw);
        });
    }

    /* ---------- Lightbox de la galerie ---------- */
    const modal = $('[data-lightbox-modal]');
    if (modal) {
        const links = $$('[data-lightbox]');
        const img = $('[data-lightbox-img]', modal);
        let index = 0, lastFocus = null;
        const show = i => {
            index = (i + links.length) % links.length;
            img.src = links[index].href;
            img.alt = links[index].querySelector('img')?.alt || '';
        };
        const open = i => { lastFocus = document.activeElement; show(i); modal.hidden = false; document.body.style.overflow = 'hidden'; $('[data-lightbox-close]', modal).focus(); };
        const close = () => { modal.hidden = true; document.body.style.overflow = ''; lastFocus && lastFocus.focus(); };
        links.forEach((a, i) => a.addEventListener('click', e => { e.preventDefault(); open(i); }));
        $('[data-lightbox-close]', modal).addEventListener('click', close);
        $('[data-lightbox-prev]', modal).addEventListener('click', () => show(index - 1));
        $('[data-lightbox-next]', modal).addEventListener('click', () => show(index + 1));
        modal.addEventListener('click', e => { if (e.target === modal) close(); });
        document.addEventListener('keydown', e => {
            if (modal.hidden) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') show(index - 1);
            if (e.key === 'ArrowRight') show(index + 1);
        });
        if (links.length < 2) $$('.lightbox-nav', modal).forEach(b => b.hidden = true);
    }

    /* ---------- Formulaire de contact ---------- */
    const form = $('[data-contact-form]');
    if (form) {
        const alertBox = $('[data-form-alert]', form);
        const submit = $('[data-submit]', form);
        const label = $('[data-submit-label]', form);
        const success = $('[data-form-success]');
        const fileInput = $('#attachment', form);
        const drop = $('[data-file-drop]', form);
        const fileName = $('[data-file-name]', form);
        const defaultFileText = fileName ? fileName.textContent : '';
        const defaultLabel = label.textContent;

        if (fileInput) {
            fileInput.addEventListener('change', () => {
                const f = fileInput.files[0];
                drop.classList.toggle('has-file', !!f);
                fileName.textContent = f ? `${f.name} — ${(f.size / 1024 / 1024).toFixed(2)} Mo` : defaultFileText;
            });
            ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, () => drop.classList.add('is-drag')));
            ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, () => drop.classList.remove('is-drag')));
        }

        const setError = (name, message) => {
            const err = $(`#err-${name}`, form);
            const field = form.elements[name];
            if (err) { err.textContent = message || ''; err.hidden = !message; }
            if (field && field.setAttribute && !(field instanceof RadioNodeList)) {
                message ? field.setAttribute('aria-invalid', 'true') : field.removeAttribute('aria-invalid');
            }
        };
        const clearErrors = () => {
            $$('.field-error', form).forEach(e => { e.textContent = ''; e.hidden = true; });
            $$('[aria-invalid]', form).forEach(e => e.removeAttribute('aria-invalid'));
            alertBox.hidden = true;
        };
        $$('input, select, textarea', form).forEach(el => el.addEventListener('input', () => setError(el.name, '')));

        const getToken = () => new Promise(resolve => {
            const mode = form.dataset.recaptcha;
            if (mode === 'v2') {
                resolve(window.grecaptcha ? grecaptcha.getResponse() : '');
            } else if (mode === 'v3' && window.grecaptcha) {
                grecaptcha.ready(() => grecaptcha.execute(form.dataset.sitekey, { action: 'contact' }).then(resolve).catch(() => resolve('')));
            } else {
                resolve('');
            }
        });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            clearErrors();

            // Validation rapide côté client (la validation de référence est faite côté serveur)
            let firstInvalid = null;
            $$('[required]', form).forEach(el => {
                const empty = el.type === 'checkbox' ? !el.checked : !el.value.trim();
                const badEmail = el.type === 'email' && el.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value);
                if (empty || badEmail) {
                    setError(el.name, el.type === 'checkbox' ? form.dataset.msgConsent || 'Ce champ est obligatoire.' : (badEmail ? 'Adresse email invalide.' : 'Ce champ est obligatoire.'));
                    firstInvalid = firstInvalid || el;
                }
            });
            if (firstInvalid) { firstInvalid.focus(); return; }

            submit.disabled = true;
            submit.classList.add('is-loading');
            label.textContent = 'Envoi en cours…';

            try {
                form.elements.recaptcha_token.value = await getToken();
                const res = await fetch(form.action, {
                    method: 'POST', body: new FormData(form),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({ success: false }));
                if (data.success) {
                    form.hidden = true;
                    if (data.message) $('[data-success-text]', success).textContent = data.message;
                    success.hidden = false;
                    success.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
                    return;
                }
                const errors = data.errors || {};
                Object.keys(errors).forEach(k => k !== '_form' && setError(k, errors[k]));
                alertBox.textContent = data.message || 'Une erreur est survenue. Merci de réessayer.';
                alertBox.hidden = false;
                const first = Object.keys(errors).find(k => k !== '_form' && form.elements[k]);
                (first && form.elements[first].focus) ? form.elements[first].focus() : alertBox.scrollIntoView({ block: 'center' });
                if (form.dataset.recaptcha === 'v2' && window.grecaptcha) grecaptcha.reset();
            } catch (err) {
                alertBox.textContent = 'Connexion impossible. Vérifiez votre réseau puis réessayez.';
                alertBox.hidden = false;
            } finally {
                submit.disabled = false;
                submit.classList.remove('is-loading');
                label.textContent = defaultLabel;
            }
        });
    }
})();
