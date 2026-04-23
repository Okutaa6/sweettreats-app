@if (session('group_name') === 'Sales')
    <style>
        .st-complaint-fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
            border: none;
            box-shadow: 0 8px 20px rgba(70, 21, 0, 0.35);
            cursor: pointer;
            z-index: 9998;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            transition: all 0.25s ease;
        }
        .st-complaint-fab:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(70, 21, 0, 0.45); }
        .st-complaint-fab:active { transform: translateY(0); }
        .st-complaint-fab::after {
            content: 'Kirim Keluhan';
            position: absolute;
            right: 68px;
            top: 50%;
            transform: translateY(-50%);
            background: #2a1400;
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .st-complaint-fab:hover::after { opacity: 1; }

        .st-complaint-modal .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }
        .st-complaint-modal .modal-header {
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
            border-bottom: none;
            padding: 20px 24px;
        }
        .st-complaint-modal .modal-header .modal-title { color: #fff; font-weight: 700; }
        .st-complaint-modal .modal-header .close { color: #fff; opacity: 0.9; text-shadow: none; }
        .st-complaint-modal .modal-body { padding: 24px; }
        .st-complaint-modal label { font-weight: 600; font-size: 0.85rem; color: #461500; }
        .st-complaint-modal textarea {
            border: 2px solid #e8d5c4;
            border-radius: 10px;
            padding: 12px;
            font-size: 0.9rem;
            resize: vertical;
        }
        .st-complaint-modal textarea:focus {
            border-color: #461500;
            outline: none;
            box-shadow: 0 0 0 3px rgba(70, 21, 0, 0.1);
        }
        .st-complaint-modal .char-count { font-size: 0.75rem; color: #8b7a6b; text-align: right; margin-top: 4px; }
        .st-complaint-modal .modal-footer { border-top: 1px solid #f0ebe5; padding: 16px 24px; }
    </style>

    <button type="button" class="st-complaint-fab" data-toggle="modal" data-target="#complaintModal" title="Kirim Keluhan">
        <i class="dripicons-conversation"></i>
    </button>

    <div class="modal fade st-complaint-modal" id="complaintModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="complaintForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="dripicons-conversation"></i>&nbsp; Kirim Keluhan/Pertanyaan ke Team IT</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-2">
                            <label>Isi Keluhan / Pesan</label>
                            <textarea class="form-control" name="message" id="complaintMessage"
                                rows="5" maxlength="2000" required minlength="5"
                                placeholder="Tuliskan keluhan atau masalah yang kamu hadapi..."></textarea>
                            <div class="char-count"><span id="complaintCharCount">0</span> / 2000</div>
                        </div>
                        <div class="alert alert-light py-2 px-3 mb-0" style="font-size:0.78rem; background:#fef5ed; border:1px solid #f0e4d4; color:#6b4a2a;">
                            <i class="dripicons-information"></i>
                            Terima kasih ya sudah meluangkan waktu untuk menyampaikan keluhannya. Masukan kamu sangat berarti dan akan dikirim ke tim IT sebagai bahan evaluasi untuk perbaikan sistem.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="complaintSubmitBtn" style="background:linear-gradient(135deg,#461500,#6b2400); border:none;">
                            <i class="dripicons-checkmark"></i>&nbsp; Kirim Keluhan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var $textarea = $('#complaintMessage');
        var $count    = $('#complaintCharCount');
        var $form     = $('#complaintForm');
        var $btn      = $('#complaintSubmitBtn');

        $textarea.on('input', function () {
            $count.text($(this).val().length);
        });

        $('#complaintModal').on('shown.bs.modal', function () {
            $textarea.trigger('focus');
        }).on('hidden.bs.modal', function () {
            $textarea.val('');
            $count.text('0');
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            var message = $textarea.val().trim();
            if (message.length < 5) {
                Swal.fire({ icon: 'warning', title: 'Keluhan terlalu pendek', text: 'Minimal 5 karakter.', confirmButtonColor: '#461500' });
                return;
            }
            $btn.prop('disabled', true).html('<i class="dripicons-clockwise"></i>&nbsp; Mengirim...');
            $.ajax({
                url: "{{ route('complaint.store') }}",
                method: 'POST',
                data: { message: message, _token: '{{ csrf_token() }}' },
                success: function (res) {
                    $('#complaintModal').modal('hide');
                    Swal.fire({ icon: 'success', title: 'Terkirim', text: res.message || 'Keluhan berhasil dikirim.', confirmButtonColor: '#461500' });
                },
                error: function (xhr) {
                    var msg = 'Gagal mengirim keluhan. Coba lagi nanti.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    else if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.message) msg = xhr.responseJSON.errors.message[0];
                    Swal.fire({ icon: 'error', title: 'Gagal', text: msg, confirmButtonColor: '#461500' });
                },
                complete: function () {
                    $btn.prop('disabled', false).html('<i class="dripicons-checkmark"></i>&nbsp; Kirim Keluhan');
                }
            });
        });
    })();
    </script>
@endif
