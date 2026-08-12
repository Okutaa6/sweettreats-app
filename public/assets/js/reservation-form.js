/**
 * reservation-form.js — logika form Edit/Tambah Reservasi.
 *
 * Diekstrak VERBATIM dari inline @section('js') edit.blade.php (Prereq#2b).
 * Dibungkus jadi window.initReservationForm() supaya bisa dipanggil oleh:
 *   - halaman standalone (edit/new) setelah DOM + plugin (jQuery/select2/flatpickr/Swal) siap.
 *   - drawer (Fase 1) setelah markup di-inject.
 *
 * Semua nilai dari Blade dibaca dari window.rrFormConfig (lihat script config di markup).
 * loadSlots() & deleteReservation() di-expose ke window.* karena dipanggil inline onclick.
 *
 * CATATAN:
 *   - Prereq#2b: ekstraksi EDIT verbatim. Prereq#2c: mode 'add' digabung (waiting-list +
 *     qp-phone-dari-pasien-terpilih). Cabang mode lewat cfg.mode ('edit'|'add').
 *     Sebagian besar logika 'add' AMAN jalan di 'edit' (di-guard keberadaan elemen → no-op),
 *     jadi cfg.mode cuma dipakai di tempat yang benar-benar beda (sumber No.HP quick-patient).
 *   - Bind-once / namespaced .off().on() utk anti double-bind saat drawer re-open
 *     BELUM dikerjain — ditambah pas wiring drawer (saat re-invoke benar-benar terjadi & bisa dites).
 */
window.initReservationForm = function () {
    var cfg = window.rrFormConfig || {};

    // Bind-once: bersihkan handler delegated dari init sebelumnya (mis. drawer dibuka
    // ulang) biar tidak menumpuk/double-fire. Semua $(document).on di bawah pakai
    // namespace .rrform supaya off ini cuma nyabut handler form, bukan handler list.
    $(document).off('.rrform');

    // Notice muncul saat cabang diganti
    const originalBranchId = cfg.originalBranchId;
    $('#branchSelect2').on('change', function () {
        if ($(this).val() !== originalBranchId) {
            $('#branchChangeNotice').show();
        } else {
            $('#branchChangeNotice').hide();
        }
    });

    function deleteReservation() {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data reservasi yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteReservation').submit();
            }
        })
    }
    window.deleteReservation = deleteReservation; // dipanggil inline onclick

    // LAYANAN CHECKLIST
    // Hitung total durasi dari semua layanan yang diceklis
    function updateTotalDuration() {
        let total = 0;
        let names = [];

        $('.svc-checkbox:checked').each(function() {
            const svcId = $(this).data('svc-id');
            const detailCheckboxes = $('#detailWrap_' + svcId + ' .svc-detail-checkbox:checked');
            const defDur = parseFloat($(this).data('default-dur')) || 0;

            let subServiceDurations = 0;
            if (detailCheckboxes.length > 0) {
                detailCheckboxes.each(function() {
                    const detailId = $(this).val();
                    const qtyStr = $('input[name="services[' + svcId + '][qty][' + detailId + ']"]').val();
                    const qty = parseInt(qtyStr) || 1;
                    subServiceDurations += (parseFloat($(this).data('dur')) || 0) * qty;
                });
            }

            total += (subServiceDurations > 0) ? subServiceDurations : defDur;
            names.push($(this).data('svc-name'));
        });
        if (total > 0) {
            $('#totalDurationInfo').html(
                '<i class="fa fa-clock"></i> '
                + '<strong>Total durasi: ' + total + ' menit</strong>'
                + '<span style="color:var(--text-subtle);font-weight:500;margin-left:6px;">(' + names.join(', ') + ')</span>'
            ).show();
        } else {
            $('#totalDurationInfo').html('Belum ada layanan dipilih').hide();
        }

        // auto-refresh slot karena durasi berubah
        const newDuration = total || cfg.totalDuration;
        const durationChanged = newDuration !== totalDuration;
        totalDuration = newDuration;

        if (allSlots.length > 0) {
            if (durationChanged) {
                // interval slot tergantung durasi → harus re-fetch dari API
                loadSlots();
            } else {
                reRenderSlots();
            }
        }

        // Refresh tampilan jam & end_hour kalau slot sudah dipilih, supaya
        // "JADWAL AKTIF" ikut berubah saat durasi layanan diganti (bukan cuma
        // saat klik slot). Sebelumnya hilang di edit → jam mentok di nilai awal.
        if (selectedStart) {
            const end = addMinutes(selectedStart, totalDuration);
            $('#endHourInput').val(end);
            $('#currentTime').text(selectedStart + ' – ' + end);
        }
    }

    // render checklist layanan dari services yang dokter bisa lakukan
    function renderServiceChecklist(services) {
        if (!services || services.length === 0) {
            $('#serviceChecklist').html(
                '<div class="text-warning"><i class="fa fa-exclamation-triangle"></i> '
                + 'Dokter ini belum punya layanan yang bisa dilakukan. '
                + 'Tambahkan di halaman <a href="/rawdoctor" target="_blank">Data Doctor</a>.</div>'
            );
            updateTotalDuration();
            return;
        }

        let html = '<div class="st-service-list">';
        services.forEach(function(svc) {
            const existing       = (window.existingServices || {})[svc.id] || null;
            const isChecked      = existing !== null;
            const existDetailIds = existing ? (existing.detail_ids || []) : [];

            html += '<div class="st-service-row ' + (isChecked ? 'is-checked' : '') + '" '
                  + 'id="svcRow_' + svc.id + '">';

            html += '<div class="st-service-row__top">';

            // Durasi efektif (override per dokter dari pivot kalau ada, else global)
            const effectiveDur = (svc.effective_duration_minutes !== undefined && svc.effective_duration_minutes !== null)
                ? svc.effective_duration_minutes
                : svc.duration_minutes;

            // checkbox layanan
            html += '<div class="custom-control custom-checkbox">'
                  + '<input type="checkbox" class="custom-control-input svc-checkbox" '
                  + 'id="svc_' + svc.id + '" '
                  + 'name="services[' + svc.id + '][checked]" '
                  + 'value="1" '
                  + 'data-svc-id="' + svc.id + '" '
                  + 'data-svc-name="' + svc.name + '" '
                  + 'data-default-dur="' + effectiveDur + '" '
                  + (isChecked ? 'checked' : '') + '>'
                  + '<label class="custom-control-label" for="svc_' + svc.id + '">'
                  + '<strong>' + svc.name + '</strong>'
                  + '</label></div>';

            // badge durasi & harga default
            html += '<span class="badge badge-light text-muted svc-dur-badge" id="svcDurBadge_' + svc.id + '">'
                  + '<i class="fa fa-clock"></i> ' + effectiveDur + ' menit</span>';
            html += '<span class="badge badge-light text-muted" id="svcPriceBadge_' + svc.id + '">'
                  + '<i class="fa fa-tag"></i> Rp ' + Number(svc.price).toLocaleString('id-ID') + '</span>';

            html += '</div>'; // end st-service-row__top

            // baris sub-detail — muncul kalau diceklis
            html += '<div class="svc-detail-wrap" id="detailWrap_' + svc.id + '" '
                  + 'style="' + (isChecked ? '' : 'display:none') + '">';
            html += '<div>';

            if (svc.details && svc.details.length > 0) {
                // Cek apakah ada sub-detail yang sudah dicentang untuk membuka area ini otomatis
                const hasCheckedDetail = svc.details.some(function(d) { return (existDetailIds || []).includes(d.id); });
                const collapseClass = hasCheckedDetail ? 'show' : '';

                html += '<div class="d-flex align-items-center justify-content-between mb-1">';
                html += '  <label class="small text-muted mb-0">Sub-detail Perawatan <span class="text-muted">(opsional)</span></label>';
                html += '  <a href="#collapseSubSvc_' + svc.id + '" data-toggle="collapse" class="small text-primary" style="text-decoration:none;"><i class="fa fa-list"></i> Tampilkan/Sembunyikan</a>';
                html += '</div>';

                // Wrap dalam collapse dan beri max-height dengan scrollbar
                html += '<div class="collapse ' + collapseClass + '" id="collapseSubSvc_' + svc.id + '">';

                // Input pencarian sub-detail
                html += '<div class="mb-1 mt-1">';
                html += '<input type="text" class="sub-detail-search" placeholder="Cari sub-detail..." data-target="#listSubSvc_' + svc.id + '">';
                html += '</div>';

                html += '<div class="sub-service-checklist mt-1" id="listSubSvc_' + svc.id + '">';

                svc.details.forEach(function(d) {
                    const existingDetail = existing && existing.details ? existing.details.find(function(e) { return e.id == d.id; }) : null;
                    const isDetailChecked = !!existingDetail || (existDetailIds || []).includes(d.id);
                    const qty = existingDetail ? (existingDetail.quantity || 1) : 1;
                    const safeName = d.name.replace(/"/g, '&quot;').toLowerCase();

                    html += '<div class="d-flex align-items-center justify-content-between mb-1 sub-detail-item" data-name="' + safeName + '">';
                    html += '<div class="custom-control custom-checkbox">';
                    const detailDur = (d.effective_duration_minutes !== undefined && d.effective_duration_minutes !== null)
                        ? d.effective_duration_minutes
                        : d.duration_minutes;
                    html += '<input type="checkbox" class="custom-control-input svc-detail-checkbox" '
                          + 'id="svc_detail_' + d.id + '" '
                          + 'name="services[' + svc.id + '][detail_id][]" '
                          + 'value="' + d.id + '" '
                          + 'data-svc-id="' + svc.id + '" '
                          + 'data-dur="' + detailDur + '" '
                          + (isDetailChecked ? 'checked' : '') + '>';
                    html += '<label class="custom-control-label" style="cursor:pointer;" for="svc_detail_' + d.id + '">'
                          + d.name + ' <small class="text-muted">(' + detailDur + ' mnt — Rp ' + Number(d.price).toLocaleString('id-ID') + ')</small>'
                          + '</label></div>';

                    html += '<div class="d-flex align-items-center" style="gap:6px; ' + (isDetailChecked ? '' : 'display:none;') + '" id="qty_wrap_' + d.id + '">';
                    html += '<small class="text-muted" style="white-space:nowrap;">Qty</small>';
                    html += '<input type="number" name="services[' + svc.id + '][qty][' + d.id + ']" value="' + qty + '" class="form-control form-control-sm qty-input" min="1" placeholder="Qty" style="width:80px;">';
                    html += '</div></div>';
                });
                html += '</div></div>';
            } else {
                html += '<label class="small text-muted mb-1">Sub-detail Perawatan <span class="text-muted">(opsional)</span></label>';
                html += '<div class="form-control form-control-sm bg-light text-muted" style="cursor:not-allowed">'
                      + 'Belum ada sub-detail — <a href="/rawdocservice/' + svc.id + '" target="_blank">Tambah di sini</a>'
                      + '</div>';
            }

            html += '</div></div>'; // end sub-detail wrap
            html += '</div>'; // end service-row
        });
        html += '</div>'; // end st-service-list

        $('#serviceChecklist').html(html);

        updateTotalDuration();
    }

    // load layanan berdasarkan dokter yang dipilih di header
    function loadServicesByDoctor() {
        // prioritaskan select2, fallback ke PHP-injected initial value
        const doctorId = $('#doctorSelect2').val()
                      || $('[name="raw_doctor_id"]').val()
                      || window.initialDoctorId
                      || '';

        if (!doctorId) {
            $('#serviceChecklist').html(
                '<span class="text-muted">Pilih dokter terlebih dahulu untuk melihat layanan yang tersedia</span>'
            );
            return;
        }

        $('#serviceChecklist').html(
            '<span class="text-muted"><i class="fa fa-spinner fa-spin"></i> Memuat layanan dokter...</span>'
        );

        // branch_id WAJIB dikirim supaya API baca override durasi
        // per-dokter PER-CABANG (raw_doctor_branch_service_duration).
        // Tanpa ini, durasi yang diatur di edit-dokter tidak terbaca
        // dan API fallback ke durasi default service.
        const branchId = $('#branchSelect2').val() || $('[name="raw_branch_id"]').val() || '';
        const url = '/api/reservation/services-by-doctor/' + doctorId
                  + (branchId ? '?branch_id=' + encodeURIComponent(branchId) : '');
        fetch(url)
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(services) {
                try {
                    renderServiceChecklist(services);
                } catch(renderErr) {
                    console.error('renderServiceChecklist error:', renderErr);
                    $('#serviceChecklist').html(
                        '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> '
                        + 'Render error: ' + renderErr.message + '</span>'
                    );
                }
            })
            .catch(function(err) {
                console.error('fetch error:', err);
                $('#serviceChecklist').html(
                    '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> '
                    + 'Fetch error: ' + err.message + '</span>'
                );
            });
    }

    // pas checkbox layanan di-toggle
    $(document).on('change.rrform', '.svc-checkbox', function() {
        const svcId   = $(this).data('svc-id');
        const checked = $(this).is(':checked');
        const row     = $('#svcRow_' + svcId);
        const wrap    = $('#detailWrap_' + svcId);

        if (checked) {
            wrap.show();
            row.addClass('is-checked');
        } else {
            wrap.hide();
            wrap.find('input[type="checkbox"]').prop('checked', false); // Uncheck all sub-services
            row.removeClass('is-checked');
        }

        updateTotalDuration();
    });

    // pas sub-detail (checkbox) berubah -> update total durasi
    $(document).on('change.rrform', '.svc-detail-checkbox', function() {
        const isChecked = $(this).is(':checked');
        const detailId = $(this).val();
        const qtyWrap = $('#qty_wrap_' + detailId);

        if (isChecked) {
            qtyWrap.show();
        } else {
            qtyWrap.hide();
        }

        updateTotalDuration();
    });

    // Update durasi saat input quantity diubah
    $(document).on('input.rrform change.rrform', '.qty-input', function() {
        updateTotalDuration();
    });

    // Fitur pencarian sub-detail
    $(document).on('input.rrform', '.sub-detail-search', function() {
        const keyword = $(this).val().toLowerCase();
        const targetId = $(this).data('target');

        $(targetId).find('.sub-detail-item').each(function() {
            const name = String($(this).data('name') || '');
            if (name.includes(keyword)) {
                $(this).removeClass('d-none').addClass('d-flex');
            } else {
                $(this).removeClass('d-flex').addClass('d-none');
            }
        });
    });
    // END LAYANAN CHECKLIST

    // ===== SLOT PICKER FOR RESCHEDULE =====
    let totalDuration = cfg.totalDuration; // let bukan const karena di-update saat layanan berubah
    let   allSlots      = [];
    let   selectedStart = cfg.selectedStart;
    let   datePicker    = null;   // flatpickr instance
    let   waitingAuto   = false;  // (mode add) tandai centang waiting yang di-set otomatis

    // Helper: "HH:MM" → total menit
    function toMinutes(t) {
        const [h, m] = (t || '00:00').split(':').map(Number);
        return h * 60 + m;
    }
    // Helper: tambah menit ke "HH:MM"
    function addMinutes(t, mins) {
        const total = toMinutes(t) + mins;
        return String(Math.floor(total / 60)).padStart(2,'0') + ':' + String(total % 60).padStart(2,'0');
    }

    // Cek apakah slot [slotStart, slotStart+totalDuration] overlap dengan range [bookedStart, bookedEnd]
    function slotWouldConflict(slotStart, bookedRanges) {
        const sStart = toMinutes(slotStart);
        const sEnd   = sStart + totalDuration;
        return bookedRanges.some(function(r) {
            return sStart < toMinutes(r.end) && sEnd > toMinutes(r.start);
        });
    }

    // ── Auto waiting-list (khusus mode 'add') ───────────────────────────────
    // AMAN dipanggil di mode 'edit' juga: kalau elemen #is_waiting / banner /
    // submit-label tidak ada (markup edit), fungsi early-return / no-op.
    function syncWaitingUI(isFull) {
        const cb = document.getElementById('is_waiting');
        const on = cb ? cb.checked : false;
        const banner = document.getElementById('waitingBanner');
        if (banner) banner.style.display = isFull ? 'block' : 'none';
        const lbl = document.getElementById('submitReservationLabel');
        if (lbl) lbl.textContent = on ? 'Masukkan ke Waiting List' : 'Simpan Reservasi';
        const icon = document.querySelector('#submitReservationBtn i');
        if (icon) icon.className = on ? 'fa fa-hourglass-half' : 'fa fa-save';
        // Waiting list → tanggal boleh dikosongkan (server: nullable saat is_waiting=1).
        const dateInput = document.getElementById('datePickerReschedule');
        if (dateInput) dateInput.placeholder = on ? 'Opsional untuk waiting list…' : 'Pilih tanggal...';
    }

    function refreshWaitingState(isFull) {
        const cb = document.getElementById('is_waiting');
        if (!cb) return; // mode edit: tidak ada checkbox waiting → no-op
        if (isFull && !cb.checked) { cb.checked = true; waitingAuto = true; }
        else if (!isFull && waitingAuto) { cb.checked = false; waitingAuto = false; }
        syncWaitingUI(isFull);
    }

    function slotsAreFull() {
        // Penuh = sudah pernah load slot tapi tak ada satupun tersedia,
        // ATAU memang tidak ada slot sama sekali untuk jadwal ini.
        if (!allSlots || allSlots.length === 0) return true;
        return !allSlots.some(function (s) { return !s.is_booked && !s.is_past; });
    }

    // ---- DATE PICKER: hanya izinkan hari sesuai jadwal dokter ----
    function initDatePicker(enabledDates) {
        if (datePicker) datePicker.destroy();

        const config = {
            dateFormat: 'Y-m-d',
            disableMobile: true,
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr) {
                loadSlots();
            }
        };

        if (enabledDates && enabledDates.length > 0) {
            // Whitelist: hanya tanggal yang ada di array
            config.enable = enabledDates;
            $('#datePickerInfo').html(
                '<i class="fa fa-calendar-check text-success"></i> '
                + enabledDates.length + ' tanggal tersedia dalam 90 hari ke depan'
            );
        } else {
            // Kalau belum ada dokter/cabang, disable semua
            config.enable = [];
            $('#datePickerInfo').html(
                '<i class="fa fa-info-circle text-muted"></i> Pilih dokter dan cabang terlebih dahulu'
            );
        }

        datePicker = flatpickr('#datePickerReschedule', config);
    }

    function loadDoctorDates() {
        const doctorId = $('#doctorSelect2').val() || $('[name="raw_doctor_id"]').val();
        const branchId = $('#branchSelect2').val() || $('[name="raw_branch_id"]').val();

        if (!doctorId || !branchId) {
            initDatePicker([]);
            $('#slotsContainer').html('');
            return;
        }

        $('#datePickerInfo').html('<i class="fa fa-spinner fa-spin"></i> Memuat jadwal dokter...');

        fetch('/api/reservation/dates/' + branchId + '/' + doctorId)
            .then(function(r) { return r.json(); })
            .then(function(dates) {
                const dateStrings = dates.map(function(d) { return d.date; });
                initDatePicker(dateStrings);
                // Kalau tanggal yang sedang aktif tidak valid untuk dokter ini, kosongkan
                const currentDate = $('[name="date"]').val();
                if (currentDate && !dateStrings.includes(currentDate)) {
                    if (datePicker) datePicker.clear();
                    $('#slotsContainer').html(
                        '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> '
                        + 'Tanggal sebelumnya tidak sesuai jadwal dokter. Pilih tanggal baru.</span>'
                    );
                } else {
                    loadSlots();
                }
            })
            .catch(function() {
                initDatePicker([]);
            });
    }

    // ---- SLOT RENDERING ----
    function renderSlots(data) {
        allSlots = data.slots || [];
        if (allSlots.length === 0) {
            $('#slotsContainer').html('<span class="text-muted">Tidak ada slot tersedia untuk jadwal ini</span>');
            refreshWaitingState(true); // mode add: tandai penuh (no-op di edit)
            return;
        }
        reRenderSlots();
        refreshWaitingState(slotsAreFull()); // mode add (no-op di edit)
    }

    function reRenderSlots() {
        // kumpulin semua range yang SUDAH terisi (dari API)
        const bookedRanges = allSlots
            .filter(function(s) { return s.is_booked; })
            .map(function(s) { return { start: s.time, end: s.end_time }; });

        const selectedEnd = selectedStart ? addMinutes(selectedStart, totalDuration) : null;

        let html = '<div class="st-slots">';

        allSlots.forEach(function(slot) {
            let variant, extraAttr = '', label;
            const slotStart = slot.time;

            // Apakah slot ini masuk dalam range durasi yang sedang dipilih user?
            const isInSelectedRange = selectedEnd
                && toMinutes(slotStart) > toMinutes(selectedStart)
                && toMinutes(slotStart) < toMinutes(selectedEnd);

            const isSelectedSlot = slotStart === selectedStart;

            // Apakah memilih slot ini akan menabrak booking yang ada?
            const wouldConflict = !slot.is_booked && !slot.is_past
                && slotWouldConflict(slotStart, bookedRanges);

            if (slot.is_booked) {
                variant   = 'st-slot--booked';
                extraAttr = 'disabled';
                label     = slotStart + ' <small>Terisi</small>';
            } else if (slot.is_past) {
                variant   = 'st-slot--past';
                extraAttr = 'disabled';
                label     = slotStart + ' <small>Lewat</small>';
            } else if (wouldConflict) {
                variant   = 'st-slot--conflict';
                extraAttr = 'disabled title="Jika dipilih, durasi ' + totalDuration + ' menit akan menabrak jadwal lain"';
                label     = slotStart + ' <small>Konflik</small>';
            } else if (isSelectedSlot) {
                variant = 'st-slot--selected';
                label   = slotStart + ' <small>Dipilih</small>';
            } else if (isInSelectedRange) {
                variant   = 'st-slot--preview';
                extraAttr = 'disabled';
                label     = slotStart;
            } else {
                variant = 'st-slot--available';
                label   = slotStart;
            }

            html += '<button type="button" class="st-slot ' + variant + '" '
                  + extraAttr
                  + ' data-start="' + slotStart + '">'
                  + label + '</button>';
        });

        html += '</div>';
        html += '<div class="st-slot-legend">'
              + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--available"></span>Tersedia</span>'
              + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--selected"></span>Dipilih</span>'
              + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--conflict"></span>Konflik durasi</span>'
              + '<span class="st-slot-legend-item"><span class="st-slot-legend-dot st-slot-legend-dot--booked"></span>Terisi</span>'
              + '</div>';

        $('#slotsContainer').html(html);
    }

    function loadSlots() {
        const doctorId  = $('#doctorSelect2').val() || $('[name="raw_doctor_id"]').val();
        const branchId  = $('#branchSelect2').val() || $('[name="raw_branch_id"]').val();
        const date      = $('[name="date"]').val();
        const excludeId = cfg.excludeId;

        if (!doctorId || !date || !branchId) {
            $('#slotsContainer').html(
                '<span class="text-muted"><i class="fa fa-info-circle"></i> Lengkapi Cabang, Dokter, dan Tanggal</span>'
            );
            return;
        }

        $('#loadSlotsBtn').html('<i class="fa fa-spinner fa-spin"></i> Memuat...').prop('disabled', true);
        $('#slotsContainer').html('');

        // exclude_id cuma dikirim saat edit (cfg.excludeId terisi) supaya slot
        // reservasi yang sedang diedit tidak dihitung "terisi" oleh dirinya sendiri.
        // Mode add: excludeId null → param tak dikirim (sama seperti new.blade lama).
        const params = [];
        if (excludeId) params.push('exclude_id=' + encodeURIComponent(excludeId));
        if (totalDuration && totalDuration > 0) params.push('duration=' + encodeURIComponent(totalDuration));
        const qs = params.length ? ('?' + params.join('&')) : '';

        fetch('/api/reservation/slots/' + branchId + '/' + doctorId + '/' + date + qs)
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) { renderSlots(data); })
            .catch(function(err) {
                $('#slotsContainer').html(
                    '<span class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal: ' + err.message + '</span>'
                );
            })
            .finally(function() {
                $('#loadSlotsBtn').html('<i class="fa fa-sync"></i> Refresh Slot').prop('disabled', false);
            });
    }
    window.loadSlots = loadSlots; // dipanggil inline onclick

    // Klik slot
    $(document).on('click.rrform', '.st-slot:not([disabled])', function() {
        selectedStart = $(this).data('start');
        const end     = addMinutes(selectedStart, totalDuration);

        $('#startHourInput').val(selectedStart);
        $('#endHourInput').val(end);
        $('#currentTime').text(selectedStart + ' – ' + end);
        $('#selectedTimeDisplay').show(); // edit: sudah tampil (no-op); add: tampilkan

        // Pilih slot ber-jam → batalkan mode waiting list (no-op di edit: #is_waiting tak ada)
        const cb = document.getElementById('is_waiting');
        if (cb && cb.checked) { cb.checked = false; }
        waitingAuto = false;
        syncWaitingUI(false);

        reRenderSlots();
    });

    // Override manual waiting-list oleh user (mode add) → jangan auto-lepas lagi.
    // Mode edit: #is_waiting tak ada → handler delegated ini tak pernah fire.
    $(document).on('change.rrform', '#is_waiting', function () {
        waitingAuto = false;
        syncWaitingUI(slotsAreFull());
    });

    // ---- BRANCH FILTER BY DOCTOR ----
    function loadBranchesByDoctor(doctorId) {
        if (!doctorId) return;

        fetch('/api/reservation/branches/' + doctorId)
            .then(function(r) { return r.json(); })
            .then(function(branches) {
                const currentBranch = $('#branchSelect2').val();

                // Rebuild option list
                let opts = '<option value="">- Pilih Cabang -</option>';
                branches.forEach(function(b) {
                    const selected = b.id === currentBranch ? 'selected' : '';
                    opts += '<option value="' + b.id + '" ' + selected + '>' + b.name + '</option>';
                });
                $('#branchSelect2').html(opts);

                // Trigger select2 refresh
                if (typeof $.fn.select2 !== 'undefined') {
                    $('#branchSelect2').trigger('change.select2');
                }

                // Kalau cabang aktif tidak ada di list dokter ini → clear & info
                const validIds = branches.map(function(b) { return b.id; });
                if (currentBranch && !validIds.includes(currentBranch)) {
                    $('#branchSelect2').val('').trigger('change.select2');
                    $('#slotsContainer').html(
                        '<span class="text-warning">'
                        + '<i class="fa fa-exclamation-triangle"></i> '
                        + 'Dokter ini tidak bertugas di cabang sebelumnya. Silakan pilih cabang yang tersedia.'
                        + '</span>'
                    );
                } else {
                    // Cabang masih valid, lanjut load tanggal
                    loadDoctorDates();
                }
            })
            .catch(function() {
                // Kalau gagal fetch, biarkan dropdown apa adanya
                loadDoctorDates();
            });
    }

    // pas dokter berubah → filter cabang, reload dates & slots, reload layanan
    $('#doctorSelect2').on('select2:select select2:unselect change', function() {
        const doctorId = $(this).val();
        if (doctorId) {
            loadBranchesByDoctor(doctorId);
            loadServicesByDoctor();
        } else {
            loadDoctorDates();
        }
    });

    // pas cabang berubah (tanpa ganti dokter) → reload services (durasi override per-cabang), dates & slots
    $('#branchSelect2').on('select2:select select2:unselect change', function() {
        loadServicesByDoctor();
        loadDoctorDates();
    });

    // Saat load pertama: pakai window.initialDoctorId dari PHP — tidak bergantung select2 timing
    $(document).ready(function() {
        // Reservasi baru saja dibatalkan & ada pasien waiting list untuk
        // dokter+tanggal yang sama → tawarkan untuk meng-assign mereka.
        var p = cfg.waitlistPrompt;
        if (p) {
            var msg = 'Slot dr. ' + p.doctor + ' tanggal ' + p.date + ' kini kosong. Ada '
                    + p.count + ' pasien di waiting list. Assign sekarang?';
            if (window.Swal) {
                Swal.fire({
                    title: 'Slot kosong — ada waiting list!',
                    text: msg, icon: 'info',
                    showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
                    confirmButtonText: 'Lihat Waiting List', cancelButtonText: 'Nanti', reverseButtons: true,
                }).then(function (r) { if (r.isConfirmed) window.location.href = p.url; });
            } else if (confirm(msg)) {
                window.location.href = p.url;
            }
        }

        const initialDoctorId = window.initialDoctorId || $('#doctorSelect2').val();
        if (initialDoctorId) {
            loadBranchesByDoctor(initialDoctorId);
            loadServicesByDoctor();
        } else {
            loadDoctorDates();
        }
    });
    // ===== END SLOT PICKER =====

    // ===== CUSTOMER SELECT2 AJAX =====
    var custOpts = {
        placeholder: '- Cari Pasien -',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: cfg.customerSearchUrl,
            dataType: 'json',
            delay: 300,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data }; }
        }
    };
    // Kalau form ada di dalam drawer (z-index tinggi), render dropdown select2 DI DALAM
    // drawer juga — kalau tidak, dropdown muncul di belakang panel drawer (tak kelihatan).
    // Pola standar select2-di-dalam-modal. Di halaman standalone, closest() kosong → default body.
    var $custDrawer = $('#customerSelect').closest('#rrFormDrawer');
    if ($custDrawer.length) custOpts.dropdownParent = $custDrawer;
    $('#customerSelect').select2(custOpts);

    // ===== DOKTER & CABANG → select2 (konsisten, terutama di drawer yg tadinya native polos) =====
    // Guard: skip kalau sudah select2 (di standalone mungkin sudah di-init global) → no double-init.
    ['#doctorSelect2', '#branchSelect2'].forEach(function (sel) {
        var $el = $(sel);
        if (!$el.length || $el.hasClass('select2-hidden-accessible')) return;
        var o = { width: '100%' };
        if ($custDrawer.length) o.dropdownParent = $custDrawer;
        $el.select2(o);
    });

    // ===== QUICK ADD PASIEN DI NOMOR INI =====
    // Kartu inline untuk menambah pasien LAIN di nomor yang sama (anak/pasangan).
    // Beda dari tombol "Tambah Pasien Baru" (#rrAddPatientBtn) → side-drawer di list.blade.php.
    flatpickr('#qpTgl', {
        dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y',
        maxDate: 'today', disableMobile: true
    });

    function qpOpen()  { $('#quickPatientBox').slideDown(150); $('#toggleQuickPatient').hide(); $('#qpName').focus(); }
    function qpClose() { $('#quickPatientBox').slideUp(150); $('#toggleQuickPatient').show(); $('#qpStatus').empty(); }

    $('#toggleQuickPatient').on('click', function (e) { e.preventDefault(); qpOpen(); });
    $('#qpCloseBtn, #qpCancelBtn').on('click', function () { qpClose(); });

    // (mode add) ambil No.HP dari teks pasien lama yang terpilih di select2,
    // karena markup 'add' tidak punya input #qpPhone (1 nomor → banyak pasien).
    function qpExtractPhoneFromSelected() {
        const text = $('#customerSelect option:selected').text() || '';
        const m = text.match(/\(([^)]+)\)\s*$/);
        const phone = m ? m[1].trim() : '';
        return phone && phone !== '-' ? phone : '';
    }

    $('#qpSaveBtn').on('click', function () {
        const $btn = $(this);
        // Sumber No.HP beda per mode: edit punya input #qpPhone; add ambil dari pasien terpilih.
        const phone = (cfg.mode === 'add')
            ? qpExtractPhoneFromSelected()
            : ($('#qpPhone').val() || '').trim();
        const payload = {
            _token:        $('input[name=_token]').first().val(),
            name:          $('#qpName').val().trim(),
            phone:         phone,
            email:         $('#qpEmail').val().trim(),
            jenis_kelamin: $('#qpJk').val(),
            tanggal_lahir: $('#qpTgl').val(),
            relasi:        $('#qpRelasi').val()
        };
        if (!payload.name) {
            $('#qpStatus').html('<span class="qp-msg qp-err">Nama pasien wajib diisi.</span>');
            return;
        }
        if (!payload.phone) {
            const msgNoPhone = (cfg.mode === 'add')
                ? 'Pilih pasien lama dulu untuk mengambil nomor HP.'
                : 'No. HP wajib diisi.';
            $('#qpStatus').html('<span class="qp-msg qp-err">' + msgNoPhone + '</span>');
            return;
        }
        const html = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
        $.post(cfg.quickPatientUrl, payload, function (res) {
            const opt = new Option(res.text, res.id, true, true);
            $('#customerSelect').append(opt).trigger('change');
            $('#qpName,#qpPhone,#qpEmail').val('');
            if ($('#qpTgl')[0]._flatpickr) $('#qpTgl')[0]._flatpickr.clear();
            $('#qpStatus').html('<span class="qp-msg qp-ok"><i class="fa fa-check-circle"></i> Pasien ditambahkan &amp; dipilih.</span>');
            setTimeout(qpClose, 900);
        }).fail(function (xhr) {
            let msg = 'Gagal menyimpan pasien.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).map(function (a) { return a[0]; }).join(' ');
            }
            $('#qpStatus').html('<span class="qp-msg qp-err">' + msg + '</span>');
        }).always(function () {
            $btn.prop('disabled', false).html(html);
        });
    });
};
