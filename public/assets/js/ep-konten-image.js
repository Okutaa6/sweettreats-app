/**
 * EP Konten — Foto/Gambar Upload + Review Handler
 *
 * Behavior:
 *   - Drag/drop & click-to-pick upload (auto-save AJAX)
 *   - Inline review panel per-foto (Disetujui / Perlu Revisi)
 *   - Delete dengan confirm + remove dari DOM
 *   - Eye icon → lightbox modal
 *
 * Config di-inject lewat window.EP_KONTEN_IMG_CONFIG (lihat _image_section.blade.php).
 */
(function () {
    'use strict';

    const cfg = window.EP_KONTEN_IMG_CONFIG;
    if (!cfg || !cfg.kontenId) return;

    // Sanity check: cfg harus berisi path image API.
    console.log('[ep-konten-image] script loaded v3', { cfg });

    const MAX_SIZE_MB = 5;
    const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    const dropzone   = document.getElementById('epImgDropzone');
    const fileInput  = document.getElementById('epImgInput');
    const pickBtn    = document.getElementById('epImgPickBtn');
    const list       = document.getElementById('epImgList');
    const progress   = document.getElementById('epImgProgress');
    const progressFill = document.getElementById('epImgProgressFill');
    const progressText = document.getElementById('epImgProgressText');
    const lightbox     = document.getElementById('epImgLightbox');
    const lightboxImg  = document.getElementById('epImgLightboxImg');
    const lightboxClose= document.getElementById('epImgLightboxClose');

    if (!dropzone || !fileInput || !list) return;

    // ─── Dropzone: click & drag-drop ─────────────────────────────────────
    dropzone.addEventListener('click', (e) => {
        if (e.target.closest('a, button')) return; // klik di link jangan double-trigger
        fileInput.click();
    });
    if (pickBtn) {
        pickBtn.addEventListener('click', (e) => {
            e.preventDefault();
            fileInput.click();
        });
    }

    ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('is-dragover');
        });
    });
    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (files && files.length) uploadFiles(files);
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            uploadFiles(fileInput.files);
            fileInput.value = ''; // reset supaya bisa upload file sama lagi kalau perlu
        }
    });

    // ─── Upload via FormData + fetch ─────────────────────────────────────
    async function uploadFiles(filesList) {
        const files = Array.from(filesList);

        // Validate client-side
        const validFiles = [];
        for (const f of files) {
            if (!ALLOWED_MIMES.includes(f.type)) {
                alert(`File "${f.name}" formatnya tidak didukung. Hanya JPG, PNG, WebP.`);
                continue;
            }
            if (f.size > MAX_SIZE_MB * 1024 * 1024) {
                alert(`File "${f.name}" terlalu besar (max ${MAX_SIZE_MB} MB).`);
                continue;
            }
            validFiles.push(f);
        }
        if (!validFiles.length) return;

        const formData = new FormData();
        validFiles.forEach(f => formData.append('images[]', f));

        showProgress(true, `Mengunggah ${validFiles.length} file...`);

        try {
            const res = await fetch(cfg.urls.upload, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': cfg.csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });

            if (!res.ok) {
                // Coba ambil response sebagai JSON dulu; fallback ke text kalau bukan JSON.
                let errData = null;
                let rawText = '';
                try {
                    rawText = await res.text();
                    errData = JSON.parse(rawText);
                } catch (_) { /* response bukan JSON */ }

                console.error('[ep-konten-image] Upload failed', {
                    status: res.status,
                    statusText: res.statusText,
                    body: errData || rawText,
                });

                // Susun pesan dari struktur Laravel: { message, errors: { 'images.0': [...] } }
                let msg = errData?.message || `HTTP ${res.status}`;
                if (errData?.errors && typeof errData.errors === 'object') {
                    const flat = [];
                    Object.entries(errData.errors).forEach(([key, arr]) => {
                        (Array.isArray(arr) ? arr : [arr]).forEach(m => flat.push(`${key}: ${m}`));
                    });
                    if (flat.length) msg += '\n\n' + flat.join('\n');
                }
                throw new Error(msg);
            }

            const data = await res.json();
            (data.images || []).forEach(appendImageRow);
            (data.errors || []).forEach(msg => console.warn('Upload partial fail:', msg));

            // Hapus empty-state kalau ada
            const empty = document.getElementById('epImgEmptyHint');
            if (empty && data.images?.length) empty.remove();

        } catch (err) {
            alert(`Gagal upload: ${err.message}`);
        } finally {
            showProgress(false);
        }
    }

    function showProgress(visible, text) {
        if (!progress) return;
        progress.hidden = !visible;
        if (visible && text && progressText) progressText.textContent = text;
        // Indeterminate animation via CSS (lihat .ep-img-progress__fill --indeterminate)
        if (progressFill) progressFill.classList.toggle('is-indeterminate', visible);
    }

    // ─── Render single row HTML (mirror _image_row.blade.php) ───────────
    function appendImageRow(img) {
        const row = document.createElement('div');
        row.className = 'ep-img-slide';
        row.dataset.imageId = img.id;
        row.dataset.status  = img.review_status;
        row.innerHTML = renderRowHTML(img);
        list.appendChild(row);
        // Auto-navigate ke slide baru yang baru saja di-upload.
        const slides = getSlides();
        refreshCarousel(slides.length - 1);
    }

    function renderRowHTML(img) {
        const badgeIcon = img.review_status === 'approved' ? '<i class="fa fa-check"></i> ' :
                          img.review_status === 'revisi'   ? '<i class="fa fa-flag"></i> '  : '';
        const hasReviewNote = img.review_note && (img.review_status === 'approved' || img.review_status === 'revisi');
        const reviseNote = hasReviewNote
            ? `<div class="ep-img-review-comment" title="Catatan review"><i class="fa fa-comment-dots"></i> ${escapeHtml(img.review_note)}</div>`
            : '';
        const initialNote = hasReviewNote ? (img.review_note || '') : '';
        const clearBtn = (img.review_status === 'approved' || img.review_status === 'revisi')
            ? `<button type="button" class="ep-img-badge__clear" data-review="reset" title="Reset status ke Pending"><i class="fa fa-times"></i></button>`
            : '';

        return `
        <div class="ep-img-slide__media">
            <img src="${img.large_url || img.thumb_url}" alt="${escapeHtml(img.original_name)}" data-action="view" loading="lazy">
        </div>
        <div class="ep-img-slide__bar">
            <div class="ep-img-slide__bar-left">
                <span class="ep-img-name" title="${escapeHtml(img.original_name)}">${escapeHtml(img.original_name)}</span>
                <span class="ep-img-size">${img.size_kb} KB</span>
                <span class="ep-img-badge ep-img-badge--${img.status_color}">
                    ${badgeIcon}${(img.status_label || '').toUpperCase()}
                    ${clearBtn}
                </span>
            </div>
            <div class="ep-img-slide__bar-right">
                <a class="ep-img-icon-btn" href="${img.download_url}" title="Download"><i class="fa fa-download"></i></a>
                <button type="button" class="ep-img-icon-btn" data-action="flag" title="Review"><i class="fa fa-flag"></i></button>
                <button type="button" class="ep-img-icon-btn ep-img-icon-btn--danger" data-action="delete" title="Hapus"><i class="fa fa-trash"></i></button>
            </div>
        </div>
        <div class="ep-img-slide__sub">
            <span class="ep-img-meta">${img.created_ago || ''}</span>
            ${reviseNote}
        </div>
        <div class="ep-img-review-panel" hidden>
            <div class="ep-img-review-panel__heading">TANDAI FILE INI SEBAGAI...</div>
            <textarea class="ep-img-review-note" rows="2" placeholder="Catatan / komentar review (opsional). Mis: 'Ganti angka jadi Rp 50jt'">${escapeHtml(initialNote)}</textarea>
            <div class="ep-img-review-panel__actions">
                <button type="button" class="ep-img-rbtn ep-img-rbtn--cancel" data-review="cancel">Batal</button>
                <button type="button" class="ep-img-rbtn ep-img-rbtn--approve" data-review="approve"><i class="fa fa-check"></i> Disetujui</button>
                <button type="button" class="ep-img-rbtn ep-img-rbtn--revisi" data-review="revisi"><i class="fa fa-flag"></i> Perlu Revisi</button>
            </div>
        </div>`;
    }

    // ─── Delegated click handler ─────────────────────────────────────────
    list.addEventListener('click', async (e) => {
        const row = e.target.closest('.ep-img-slide');
        if (!row) return;

        const actionBtn = e.target.closest('[data-action]');
        const reviewBtn = e.target.closest('[data-review]');

        if (actionBtn) {
            const action = actionBtn.dataset.action;
            const id = row.dataset.imageId;

            if (action === 'view') {
                const thumb = row.querySelector('.ep-img-thumb');
                openLightbox(getLargeUrlFromThumb(thumb?.src));
            } else if (action === 'flag') {
                togglePanel(row);
            } else if (action === 'delete') {
                if (!confirm('Hapus foto ini? File akan terhapus permanen dari server.')) return;
                await deleteImage(id, row);
            }
            return;
        }

        if (reviewBtn) {
            const action = reviewBtn.dataset.review;
            const id = row.dataset.imageId;
            const panel = row.querySelector('.ep-img-review-panel');
            const note = row.querySelector('.ep-img-review-note')?.value?.trim() || '';

            if (action === 'cancel') {
                panel.hidden = true;
                return;
            }

            // Reset status (klik X di badge) → langsung set pending, clear note & audit.
            if (action === 'reset') {
                await submitReview(id, 'pending', '', row);
                return;
            }

            const status = action === 'approve' ? 'approved' : 'revisi';
            await submitReview(id, status, note, row);
        }
    });

    function togglePanel(row) {
        // Tutup panel lain dulu
        document.querySelectorAll('.ep-img-review-panel').forEach(p => {
            if (!row.contains(p)) p.hidden = true;
        });
        const panel = row.querySelector('.ep-img-review-panel');
        if (!panel) return;
        panel.hidden = !panel.hidden;
        if (!panel.hidden) {
            const ta = panel.querySelector('textarea');
            ta?.focus();
        }
    }

    async function submitReview(id, status, note, row) {
        try {
            const url = cfg.urls.review.replace('{id}', encodeURIComponent(id));
            console.log('[ep-konten-image] REVIEW URL:', url, 'id:', id, 'status:', status, 'cfg.urls.review:', cfg.urls.review);
            const res = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': cfg.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ status, note }),
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(await readErrorMessage(res, 'Review'));
            const data = await res.json();
            updateRowAfterReview(row, data.image);
        } catch (err) {
            alert(`Gagal simpan review: ${err.message}`);
        }
    }

    function updateRowAfterReview(row, img) {
        // Replace seluruh isi row dengan render baru (paling aman)
        row.dataset.status = img.review_status;
        row.innerHTML = renderRowHTML(img);
    }

    async function deleteImage(id, row) {
        try {
            const url = cfg.urls.destroy.replace('{id}', encodeURIComponent(id));
            console.log('[ep-konten-image] DELETE URL:', url, 'id:', id, 'cfg.urls.destroy:', cfg.urls.destroy);
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': cfg.csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(await readErrorMessage(res, 'Delete'));

            // Fade out lalu remove + refresh carousel state.
            row.style.transition = 'opacity 200ms';
            row.style.opacity = '0';
            setTimeout(() => {
                const slides = getSlides();
                const removedIdx = slides.indexOf(row);
                row.remove();
                // Setelah remove: kalau yang dihapus adalah current, geser ke prev (atau 0).
                const remaining = getSlides();
                let nextIdx = removedIdx;
                if (nextIdx >= remaining.length) nextIdx = remaining.length - 1;
                if (nextIdx < 0) nextIdx = 0;
                refreshCarousel(nextIdx);
            }, 220);
        } catch (err) {
            alert(`Gagal hapus: ${err.message}`);
        }
    }

    /**
     * Parse error response dari Laravel: prefer JSON {message, errors}, fallback ke text.
     * Selalu log full response ke console untuk debug.
     */
    async function readErrorMessage(res, prefix) {
        let errData = null;
        let rawText = '';
        try {
            rawText = await res.text();
            errData = JSON.parse(rawText);
        } catch (_) { /* not json */ }

        console.error(`[ep-konten-image] ${prefix} failed`, {
            status: res.status,
            statusText: res.statusText,
            url: res.url,
            body: errData || rawText,
        });

        let msg = errData?.message || `HTTP ${res.status} ${res.statusText || ''}`.trim();
        if (errData?.errors && typeof errData.errors === 'object') {
            const flat = [];
            Object.entries(errData.errors).forEach(([key, arr]) => {
                (Array.isArray(arr) ? arr : [arr]).forEach(m => flat.push(`${key}: ${m}`));
            });
            if (flat.length) msg += '\n\n' + flat.join('\n');
        }
        // Kasus umum: redirect HTML (302) = session expired / not logged in
        if (res.status === 0 || /<html/i.test(rawText)) {
            msg += '\n\n(Kemungkinan session kedaluwarsa — coba refresh halaman.)';
        }
        return msg;
    }

    // ─── Lightbox ────────────────────────────────────────────────────────
    function openLightbox(url) {
        if (!lightbox || !lightboxImg || !url) return;
        lightboxImg.src = url;
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        if (!lightbox) return;
        lightbox.hidden = true;
        lightboxImg.src = '';
        document.body.style.overflow = '';
    }
    if (lightboxClose) lightboxClose.addEventListener('click', closeLightbox);
    if (lightbox) lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox && !lightbox.hidden) closeLightbox();
    });

    // ─── Helpers ─────────────────────────────────────────────────────────
    // Thumb URL = .../thumb/xxx.webp → Large URL = .../large/xxx.webp
    function getLargeUrlFromThumb(thumbUrl) {
        if (!thumbUrl) return '';
        return thumbUrl.replace('/thumb/', '/large/');
    }

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ─── Carousel logic ──────────────────────────────────────────────────
    const carousel  = document.getElementById('epImgCarousel');
    const navPrev   = document.getElementById('epImgNavPrev');
    const navNext   = document.getElementById('epImgNavNext');
    const dotsEl    = document.getElementById('epImgDots');
    const counterEl = document.getElementById('epImgCounter');

    function getSlides() {
        return list ? Array.from(list.querySelectorAll('.ep-img-slide')) : [];
    }

    /**
     * Set state carousel ke index tertentu. Update visibility slide, dots,
     * counter, nav arrows. Handles edge cases (0 slides, 1 slide, >1 slides).
     */
    function refreshCarousel(targetIndex) {
        if (!carousel || !list) return;
        const slides = getSlides();
        const total  = slides.length;

        // Hide carousel chrome kalau tidak ada slide.
        if (total === 0) {
            carousel.classList.remove('has-multi');
            if (navPrev) navPrev.hidden = true;
            if (navNext) navNext.hidden = true;
            if (dotsEl) { dotsEl.innerHTML = ''; dotsEl.hidden = true; }
            if (counterEl) { counterEl.textContent = ''; counterEl.hidden = true; }
            return;
        }

        // Clamp index
        let idx = parseInt(targetIndex, 10);
        if (isNaN(idx) || idx < 0) idx = 0;
        if (idx >= total) idx = total - 1;
        carousel.dataset.current = String(idx);

        // Toggle is-active per slide.
        slides.forEach((sl, i) => sl.classList.toggle('is-active', i === idx));

        const multi = total > 1;
        carousel.classList.toggle('has-multi', multi);

        // Nav arrows: hanya tampil kalau >1 slide.
        if (navPrev) navPrev.hidden = !multi;
        if (navNext) navNext.hidden = !multi;

        // Dots: 1 dot per slide kalau >1, otherwise hidden.
        if (dotsEl) {
            dotsEl.hidden = !multi;
            if (multi) {
                dotsEl.innerHTML = slides.map((_, i) =>
                    `<button type="button" class="ep-img-dot ${i === idx ? 'is-active' : ''}" data-index="${i}" aria-label="Slide ${i + 1}"></button>`
                ).join('');
                // Pin dots ke media slide aktif supaya tetap di atas gambar
                // (kalau attached ke carousel, dots ikut turun saat review-panel terbuka).
                const activeMedia = slides[idx]?.querySelector('.ep-img-slide__media');
                if (activeMedia && dotsEl.parentNode !== activeMedia) {
                    activeMedia.appendChild(dotsEl);
                }
            } else {
                dotsEl.innerHTML = '';
            }
        }

        // Counter "3 / 5" di pojok kanan atas, hanya tampil kalau >1.
        if (counterEl) {
            counterEl.hidden = !multi;
            counterEl.textContent = multi ? `${idx + 1} / ${total}` : '';
        }
    }

    function currentIndex() {
        return parseInt(carousel?.dataset.current || '0', 10);
    }

    if (carousel) {
        // Initial state — show first slide.
        refreshCarousel(0);

        navPrev?.addEventListener('click', () => {
            const total = getSlides().length;
            refreshCarousel((currentIndex() - 1 + total) % total);
        });
        navNext?.addEventListener('click', () => {
            const total = getSlides().length;
            refreshCarousel((currentIndex() + 1) % total);
        });

        // Dot click via delegation.
        dotsEl?.addEventListener('click', (e) => {
            const dot = e.target.closest('.ep-img-dot');
            if (!dot) return;
            refreshCarousel(parseInt(dot.dataset.index, 10));
        });

        // Keyboard: arrow keys saat carousel di-focus.
        carousel.addEventListener('keydown', (e) => {
            if (getSlides().length < 2) return;
            if (e.key === 'ArrowLeft')  { navPrev?.click(); }
            if (e.key === 'ArrowRight') { navNext?.click(); }
        });
    }
})();
