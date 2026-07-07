<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konten Saya - {{ $affiliate->name }}</title>

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

        .sm-platform-tiles {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
        }
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

        .sm-step-list { list-style: none; display: grid; gap: 14px; }
        .sm-step-list li { display: flex; align-items: flex-start; gap: 12px; }
        .sm-step-num {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #461500, #8b3a00); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.85rem;
        }
        .sm-step-text { font-size: 0.9rem; color: #3a2a1a; line-height: 1.55; padding-top: 4px; }
        .sm-step-text strong { color: #461500; }

        .sm-content-card {
            display: flex; justify-content: space-between; gap: 14px;
            padding: 16px; border-radius: 14px; background: #fffaf5;
            border: 1px solid rgba(70,21,0,0.08); margin-bottom: 10px;
        }
        .sm-content-card.is-approved { background: linear-gradient(135deg, #f1f8f1, #e8f5e9); border-color: #a5d6a7; }
        .sm-content-card.is-rejected { background: linear-gradient(135deg, #fff5f5, #ffebee); border-color: #ef9a9a; }
        .sm-content-main { flex: 1; min-width: 0; }
        .sm-content-head { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap; }
        .sm-platform-pill {
            width: 32px; height: 32px; border-radius: 10px; flex-shrink: 0;
            background: #fff; color: #461500; display: flex;
            align-items: center; justify-content: center; font-size: 0.9rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .sm-content-url {
            color: #8b3a00; font-size: 0.85rem; font-weight: 700; word-break: break-all;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .sm-content-url:hover { color: #461500; text-decoration: underline; }
        .sm-content-meta { font-size: 0.78rem; color: #8b7a6b; margin-top: 2px; }
        .sm-content-caption { font-size: 0.85rem; color: #6b4a30; margin-top: 6px; line-height: 1.5; }
        .sm-content-notes {
            margin-top: 8px; padding: 10px 12px; border-radius: 10px;
            background: rgba(198,40,40,0.08); color: #c62828; font-size: 0.82rem; line-height: 1.5;
        }
        .sm-content-notes strong { color: #b71c1c; }

        .sm-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 50px; font-size: 0.7rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .sm-badge-pending { background: #fff8e1; color: #f57c00; }
        .sm-badge-approved { background: #e8f5e9; color: #2e7d32; }
        .sm-badge-rejected { background: #ffebee; color: #c62828; }

        .sm-content-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; }
        .sm-mini-btns { display: flex; gap: 6px; }
        .sm-mini-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 11px; border-radius: 50px; border: 1.5px solid #461500;
            background: #fff; color: #461500; font-size: 0.72rem; font-weight: 700;
            transition: all 0.25s; cursor: pointer;
            font-family: 'Montserrat', sans-serif;
        }
        .sm-mini-btn:hover { background: #461500; color: #fff; }
        .sm-mini-btn.danger { border-color: #c62828; color: #c62828; }
        .sm-mini-btn.danger:hover { background: #c62828; color: #fff; }

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
            .sm-content-card { flex-direction: column; }
            .sm-content-actions { align-items: flex-start; flex-direction: row; justify-content: space-between; width: 100%; }
        }
    </style>
</head>
<body>

    @include('affiliate._portal_navbar', ['active' => 'content'])

    <section class="sm-section">
        <div class="sm-container">

            @if(session('success'))
                <div class="sm-alert sm-alert-success">
                    <i class="fa-solid fa-check-circle"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if(session('error'))
                <div class="sm-alert sm-alert-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif
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

            {{-- HERO --}}
            <div class="sm-hero">
                <div class="sm-hero-deco sm-hero-deco-1"></div>
                <div class="sm-hero-deco sm-hero-deco-2"></div>
                <div class="sm-hero-grid">
                    <div class="sm-hero-text">
                        <div class="sm-hero-greeting">
                            <i class="fa-solid fa-video"></i>
                            Konten Behel Journey
                        </div>
                        <h1 class="sm-hero-name">Kirim Kontenmu</h1>
                        <p class="sm-hero-sub">
                            Upload di TikTok, Instagram, atau YouTube &mdash; lalu submit link-nya di sini untuk direview tim admin.
                        </p>
                    </div>
                    <div class="sm-hero-avatar">
                        <div class="sm-hero-avatar-initial"><i class="fa-solid fa-video"></i></div>
                    </div>
                </div>
            </div>

            {{-- CARA SUBMIT --}}
            <div class="sm-card sm-card-tinted">
                <div class="sm-card-title">
                    <i class="fa-solid fa-info-circle"></i>
                    Cara Kirim Konten
                </div>
                <ol class="sm-step-list">
                    <li>
                        <span class="sm-step-num">1</span>
                        <div class="sm-step-text">Upload konten behel journey kamu di <strong>TikTok / Instagram / YouTube</strong>.</div>
                    </li>
                    <li>
                        <span class="sm-step-num">2</span>
                        <div class="sm-step-text">Mention <strong>@sweet.treats.dental</strong> di caption supaya gampang diverifikasi.</div>
                    </li>
                    <li>
                        <span class="sm-step-num">3</span>
                        <div class="sm-step-text">Copy link konten, paste di form bawah, lalu klik <strong>Kirim Konten</strong>.</div>
                    </li>
                </ol>
            </div>

            {{-- FORM SUBMIT --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-paper-plane"></i>
                    Submit Konten Baru
                </div>
                <form method="POST" action="{{ route('affiliate.content.store') }}">
                    @csrf

                    <div class="sm-form-group">
                        <label class="sm-form-label">Platform</label>
                        <div class="sm-platform-tiles">
                            @foreach(\App\Models\AffiliateContent::platformOptions() as $key => $label)
                                @php
                                    $icon = $key === 'tiktok' ? 'fa-brands fa-tiktok'
                                          : ($key === 'instagram' ? 'fa-brands fa-instagram'
                                          : ($key === 'youtube' ? 'fa-brands fa-youtube' : 'fa-solid fa-share-alt'));
                                @endphp
                                <label class="sm-platform-tile {{ old('platform') === $key ? 'selected' : '' }}"
                                       onclick="document.querySelectorAll('.sm-platform-tile').forEach(t=>t.classList.remove('selected'));this.classList.add('selected');">
                                    <input type="radio" name="platform" value="{{ $key }}" {{ old('platform') === $key ? 'checked' : '' }} required>
                                    <i class="{{ $icon }}"></i>
                                    <span class="label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="content_url">Link Konten</label>
                        <input type="url" name="content_url" id="content_url" class="sm-form-control"
                               placeholder="https://www.tiktok.com/@..." value="{{ old('content_url') }}" maxlength="500" required>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="content_type">Tipe Konten</label>
                        <select name="content_type" id="content_type" class="sm-form-control" required>
                            <option value="">&mdash; Pilih Tipe &mdash;</option>
                            @foreach(\App\Models\AffiliateContent::contentTypeOptions() as $key => $label)
                                <option value="{{ $key }}" {{ old('content_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="caption">Caption / Deskripsi <span class="sm-opt">(opsional)</span></label>
                        <textarea name="caption" id="caption" class="sm-form-control" maxlength="500" placeholder="Tulis caption atau konteks kontenmu...">{{ old('caption') }}</textarea>
                    </div>

                    <button type="submit" class="sm-submit-btn">
                        <i class="fa-solid fa-paper-plane"></i> Kirim Konten
                    </button>
                </form>
            </div>

            {{-- LIST KONTEN --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-tasks"></i>
                    Riwayat Konten ({{ $contents->count() }})
                </div>

                @if($contents->isEmpty())
                    <div class="sm-inline-empty" style="flex-direction:column; gap:10px; padding:28px; text-align:center; justify-content:center;">
                        <i class="fa-solid fa-inbox" style="font-size:1.8rem;"></i>
                        <div>
                            <div style="font-weight:800; color:#461500;">Belum ada konten dikirim</div>
                            <div style="margin-top:4px;">Yuk submit konten pertama kamu &mdash; setiap konten approved bikin kamu makin dekat ke reward!</div>
                        </div>
                    </div>
                @else
                    @foreach($contents as $c)
                        @php
                            $statusCfg = \App\Models\AffiliateContent::statusOptions()[$c->status] ?? ['label' => $c->status];
                            $badgeClass = $c->status === 'pending' ? 'sm-badge-pending' : ($c->status === 'approved' ? 'sm-badge-approved' : 'sm-badge-rejected');
                            $platforms = \App\Models\AffiliateContent::platformOptions();
                            $types = \App\Models\AffiliateContent::contentTypeOptions();
                            $icon = $c->platform === 'tiktok' ? 'fa-brands fa-tiktok'
                                  : ($c->platform === 'instagram' ? 'fa-brands fa-instagram'
                                  : ($c->platform === 'youtube' ? 'fa-brands fa-youtube' : 'fa-solid fa-share-alt'));
                        @endphp
                        <div class="sm-content-card is-{{ $c->status }}">
                            <div class="sm-content-main">
                                <div class="sm-content-head">
                                    <span class="sm-platform-pill"><i class="{{ $icon }}"></i></span>
                                    <a href="{{ $c->content_url }}" target="_blank" rel="noopener" class="sm-content-url">
                                        <i class="fa-solid fa-external-link-alt"></i>
                                        {{ \Illuminate\Support\Str::limit($c->content_url, 55) }}
                                    </a>
                                </div>
                                <div class="sm-content-meta">
                                    {{ $platforms[$c->platform] ?? $c->platform }} &middot;
                                    {{ $types[$c->content_type] ?? $c->content_type }} &middot;
                                    Dikirim {{ $c->created_at?->format('d/m/Y H:i') }}
                                    @if($c->status === 'approved' && $c->reviewed_at)
                                        &middot; Disetujui {{ $c->reviewed_at->format('d/m/Y') }}
                                    @endif
                                </div>
                                @if($c->caption)
                                    <div class="sm-content-caption">{{ $c->caption }}</div>
                                @endif
                                @if($c->status === 'rejected' && $c->review_notes)
                                    <div class="sm-content-notes">
                                        <strong>Catatan Admin:</strong> {{ $c->review_notes }}
                                    </div>
                                @endif
                            </div>
                            <div class="sm-content-actions">
                                <span class="sm-badge {{ $badgeClass }}">{{ $statusCfg['label'] }}</span>
                                @if(in_array($c->status, ['pending', 'rejected'], true))
                                    <div class="sm-mini-btns">
                                        <a href="{{ route('affiliate.content.edit', $c->id) }}" class="sm-mini-btn" title="Edit konten">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>
                                        <form method="POST" action="{{ route('affiliate.content.delete', $c->id) }}"
                                              onsubmit="return confirm('Yakin mau hapus konten ini?');" style="margin:0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sm-mini-btn danger" title="Hapus konten">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

</body>
</html>
