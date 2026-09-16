<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Webhook MaxChat — pintu masuk data tanpa kredensial.
 *
 * Keadaan yang diwarisi: `if ($token && …)` berarti selama
 * `MAXCHAT_WEBHOOK_TOKEN` kosong, SELURUH pemeriksaan dilewati — siapa pun yang
 * tahu URL-nya bisa menulis ke raw_contact/raw_chat (12.404 kontak · 35.737
 * percakapan, ±859/minggu).
 *
 * Gerbong ini SENGAJA tidak menutupnya secara paksa: menolak tanpa token akan
 * menghentikan aliran MaxChat seketika kalau tokennya belum dipasang di sisi
 * mereka, dan tak ada yang sadar sampai FO mengeluh. Yang dikerjakan: membuat
 * keadaannya berbunyi, dan memastikan bahwa begitu tokennya diisi, pintunya
 * benar-benar tertutup — tanpa deploy kode lagi.
 *
 * Uji di bawah yang membuktikan kalimat terakhir itu.
 */
class WebhookMaxChatTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();   // penanda "sudah diperingatkan sejam ini"
    }

    private function kirim(array $header = [])
    {
        return $this->postJson('/api/webhooks/contact', [], $header);
    }

    public function test_1_dengan_token_terpasang_kiriman_TANPA_token_ditolak(): void
    {
        config(['services.maxchat.webhook_token' => 'rahasia-uji']);

        $this->kirim()->assertStatus(401)->assertJson(['error' => 'Unauthorized']);
    }

    public function test_2_dengan_token_terpasang_token_SALAH_ditolak(): void
    {
        config(['services.maxchat.webhook_token' => 'rahasia-uji']);

        $this->kirim(['X-Webhook-Token' => 'tebakan'])->assertStatus(401);
    }

    public function test_3_token_BENAR_lolos_gerbang(): void
    {
        config(['services.maxchat.webhook_token' => 'rahasia-uji']);

        // 422 = ditolak VALIDASI payload, artinya sudah melewati gerbang token.
        // Yang diuji di sini gerbangnya, bukan bentuk payload MaxChat.
        $this->kirim(['X-Webhook-Token' => 'rahasia-uji'])->assertStatus(422);
    }

    public function test_4_tanpa_token_di_config_aliran_TIDAK_diputus(): void
    {
        config(['services.maxchat.webhook_token' => null]);

        // Ini keadaan prod hari ini. Kalau uji ini suatu saat merah karena
        // jawabannya jadi 401, berarti seseorang membuatnya fail-closed —
        // dan itu boleh SAJA, tapi hanya setelah token dipasang di sisi MaxChat.
        // Baca catatan di MaxChatWebhookController sebelum mengubah uji ini.
        $this->kirim()->assertStatus(422);
    }

    public function test_5_kiriman_tanpa_verifikasi_MENINGGALKAN_JEJAK(): void
    {
        config(['services.maxchat.webhook_token' => null]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($pesan, $konteks = []) =>
                str_contains($pesan, 'TANPA verifikasi token')
                && ($konteks['asal'] ?? null) !== null);

        $this->kirim();
    }

    public function test_6_peringatannya_tidak_membanjiri_log(): void
    {
        config(['services.maxchat.webhook_token' => null]);

        // Satu penyerang yang menembak terus-menerus tidak boleh menenggelamkan
        // laravel.log — justru itu cara menyembunyikan kejadian lain.
        Log::shouldReceive('warning')->once();

        $this->kirim();
        $this->kirim();
        $this->kirim();
    }
}
