<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Konten - {{ $affiliate->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/affiliate-portal.css') }}?v={{ @filemtime(public_path('assets/css/affiliate-portal.css')) ?: '1' }}">

    <style>
        .sm-form-group { margin-bottom: 18px; }
        .sm-form-label {
            display: block; font-size: 0.78rem; font-weight: 700;
            color: #461500; margin-bottom: 8px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .sm-form-label .sm-opt { font-weight: 400; text-transform: none; color: #8b7a6b; letter-spacing: 0; }
        .sm-form-control {
            width: 100%; padding: 13px 16px; background: #fffaf5;
            border: 2px solid rgba(70,21,0,0.1); border-radius: 12px;
            font-family: 'Montserrat', sans-serif; font-size: 0.92rem; color: #3a2a1a;
            transition: all 0.3s; outline: none;
        }
        .sm-form-control:focus { border-color: #461500; background: #fff; box-shadow: 0 0 0 4px rgba(70,21,0,0.08); }
        textarea.sm-form-control { resize: vertical; min-height: 90px; }

        .sm-platform-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .sm-platform-tile {
            position: relative; padding: 18px 10px; border-radius: 16px;
            border: 2px solid rgba(70,21,0,0.1); background: #fffaf5;
            text-align: center; cursor: pointer; transition: all 0.25s;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }
        .sm-platform-tile:hover { border-color: #c68a56; background: #fff; }
        .sm-platform-tile input { position: absolute; opacity: 0; pointer-events: none; }
        .sm-platform-tile.selected {
            border-color: #461500; background: linear-gradient(135deg, #fef5ed, #fef0e2);
            box-shadow: 0 6px 20px rgba(70,21,0,0.1);
        }
        .sm-platform-tile i { font-size: 1.6rem; color: #461500; }
        .sm-platform-tile .label { font-size: 0.78rem; font-weight: 700; color: #461500; }

        .sm-breadcrumb { font-size: 0.82rem; color: #8b7a6b; font-weight: 600; margin-bottom: 16px; }
        .sm-breadcrumb a { color: #8b7a6b; }
        .sm-breadcrumb a:hover { color: #461500; }
        .sm-breadcrumb .sep { margin: 0 8px; }
        .sm-breadcrumb .current { color: #461500; }

        .sm-reject-note {
            padding: 16px 18px; border-radius: 16px;
            background: linear-gradient(135deg, #fff5f5, #ffebee); border: 1px solid #ef9a9a;
            color: #c62828; font-size: 0.88rem; line-height: 1.55;
            margin-bottom: 20px;
        }
        .sm-reject-note .title { display: flex; align-items: center; gap: 8px; font-weight: 800; margin-bottom: 6px; }
        .sm-reject-note .sub { color: #8b7a6b; font-size: 0.8rem; margin-top: 6px; }

        .sm-submit-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            padding: 14px 28px; border: none; border-radius: 50px;
            background: linear-gradient(135deg, #461500, #6b2400); color: #fff;
            font-family: 'Montserrat', sans-serif; font-size: 0.88rem; font-weight: 800;
            cursor: pointer; transition: all 0.3s;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .sm-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(70,21,0,0.3); }

        @media (max-width: 640px) {
            .sm-platform-tiles { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

    @include('affiliate._portal_navbar', ['active' => 'content'])

    <section class="sm-section">
        <div class="sm-container">

            <div class="sm-breadcrumb">
                <a href="{{ route('affiliate.content') }}">Konten Saya</a>
                <span class="sep">›</span>
                <span class="current">Edit Konten</span>
            </div>

            @if($errors->any())
                <div class="sm-alert sm-alert-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <div>
                        @foreach($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($content->status === 'rejected' && $content->review_notes)
                <div class="sm-reject-note">
                    <div class="title"><i class="fa-solid fa-exclamation-triangle"></i> Konten ini sebelumnya ditolak</div>
                    <div><strong>Catatan Admin:</strong> {{ $content->review_notes }}</div>
                    <div class="sub">Update konten di bawah ini, lalu klik Simpan untuk kirim ulang ke review admin.</div>
                </div>
            @endif

            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-edit"></i>
                    Edit Konten
                </div>
                <form method="POST" action="{{ route('affiliate.content.update', $content->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="sm-form-group">
                        <label class="sm-form-label">Platform</label>
                        <div class="sm-platform-tiles">
                            @foreach(\App\Models\AffiliateContent::platformOptions() as $key => $label)
                                @php
                                    $selected = old('platform', $content->platform) === $key;
                                    $icon = $key === 'tiktok' ? 'fa-brands fa-tiktok'
                                          : ($key === 'instagram' ? 'fa-brands fa-instagram'
                                          : ($key === 'youtube' ? 'fa-brands fa-youtube' : 'fa-solid fa-share-alt'));
                                @endphp
                                <label class="sm-platform-tile {{ $selected ? 'selected' : '' }}"
                                       onclick="document.querySelectorAll('.sm-platform-tile').forEach(t=>t.classList.remove('selected'));this.classList.add('selected');">
                                    <input type="radio" name="platform" value="{{ $key }}" {{ $selected ? 'checked' : '' }} required>
                                    <i class="{{ $icon }}"></i>
                                    <span class="label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="content_url">Link Konten</label>
                        <input type="url" name="content_url" id="content_url" class="sm-form-control"
                               placeholder="https://www.tiktok.com/@..."
                               value="{{ old('content_url', $content->content_url) }}" maxlength="500" required>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="content_type">Tipe Konten</label>
                        <select name="content_type" id="content_type" class="sm-form-control" required>
                            <option value="">&mdash; Pilih Tipe &mdash;</option>
                            @foreach(\App\Models\AffiliateContent::contentTypeOptions() as $key => $label)
                                <option value="{{ $key }}" {{ old('content_type', $content->content_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="caption">Caption / Deskripsi <span class="sm-opt">(opsional)</span></label>
                        <textarea name="caption" id="caption" class="sm-form-control" maxlength="500" placeholder="Tulis caption atau konteks kontenmu...">{{ old('caption', $content->caption) }}</textarea>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
                        <button type="submit" class="sm-submit-btn">
                            <i class="fa-solid fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('affiliate.content') }}" class="sm-btn sm-btn-outline">
                            <i class="fa-solid fa-arrow-left"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>

</body>
</html>
