<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Support\PeringatanTerkurung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaxChatWebhookController extends Controller
{
    public function __construct()
    {
        // Verify webhook token on all methods
        $this->middleware(function ($request, $next) {
            $token = config('services.maxchat.webhook_token');

            if ($token) {
                if (! hash_equals($token, (string) $request->header('X-Webhook-Token'))) {
                    return response()->json(['error' => 'Unauthorized'], 401);
                }

                return $next($request);
            }

            /*
             * ⛔ TOKEN BELUM DIPASANG = PINTU INI TERBUKA.
             *
             * `if ($token && …)` di atas artinya: selama config-nya kosong,
             * SELURUH pemeriksaan dilewati dan siapa pun yang tahu URL-nya bisa
             * menulis ke raw_contact/raw_chat — aliran data tersibuk kita
             * (12.404 kontak · 35.737 percakapan, ±859 per minggu), sekaligus
             * bahan CRM yang sedang dibangun.
             *
             * Ini TIDAK dijadikan fail-closed di sini, dan itu keputusan sadar:
             * menolak tanpa token akan menghentikan aliran MaxChat seketika
             * kalau tokennya belum dipasang di sisi MEREKA juga — dan tidak ada
             * yang akan sadar sampai FO mengeluh chat tidak masuk.
             *
             * Jadi yang dilakukan sekarang: membuat keadaannya BERBUNYI. Begitu
             * `MAXCHAT_WEBHOOK_TOKEN` diisi di .env prod, cabang di atas
             * langsung berlaku dan pintunya tertutup — tanpa deploy kode lagi.
             *
             * Urutan memasangnya (jangan dibalik):
             *   1. sepakati token dengan MaxChat, pasang di sisi mereka;
             *   2. isi MAXCHAT_WEBHOOK_TOKEN di .env prod + config:clear;
             *   3. pantau raw_chat beberapa jam — kalau berhenti tumbuh,
             *      tokennya tidak cocok.
             *
             * Peringatannya dikurung sekali per jam per alamat asal: tanpa itu
             * satu penyerang bisa menenggelamkan laravel.log dan justru
             * menyembunyikan kejadian lain. Aturan kurungannya hidup di
             * PeringatanTerkurung — satu definisi, dipakai juga oleh perangkap
             * bot di form publik.
             */
            PeringatanTerkurung::catat(
                'maxchat-tanpa-token:' . $request->ip(),
                'Webhook MaxChat diterima TANPA verifikasi token',
                [
                    'asal'       => $request->ip(),
                    'jalur'      => $request->path(),
                    'sebab'      => 'MAXCHAT_WEBHOOK_TOKEN kosong — pemeriksaan token dilewati',
                    'tindakan'   => 'Pasang token di sisi MaxChat lalu isi .env prod; sesudah itu pintu ini tertutup sendiri.',
                    'user_agent' => substr((string) $request->userAgent(), 0, 120),
                ]
            );

            return $next($request);
        });
    }

    public function contact(Request $request)
    {
        //  1. Validasi payload
        $validated = $request->validate([
            'id'                   => 'required|string',
            'name'                 => 'required|string',
            'channel'              => 'required|array|min:1',
            'channel.*.name'       => 'required|string',
            'channel.*.identifier' => 'required|string',
            'domain'               => 'required|string',
            'eventType'            => 'required|string',
        ]);

        $channel     = collect($validated['channel'])->first();
        $phone       = $channel['identifier'];
        $channelName = $channel['name'];
        $name        = $validated['name'];

        //  2. Cek apakah nomor sudah ada di raw_contact 
        $existing = DB::table('raw_contact')
            ->where('phone', $phone)
            ->first();

        if (! $existing) {
            //  3a. INSERT raw_contact (bukan raw_customer) 
            $rawContactId = (string) Str::uuid();

            DB::table('raw_contact')->insert([
                'id'         => $rawContactId,
                'source_app' => 'maxchat',
                'name'       => $name,
                'phone'      => $phone,
                'channel'    => $channelName,
                'created_at' => now(),
            ]);

            //  3b. INSERT raw_cus_journey → Stat_New 
            DB::table('raw_cus_journey')->insert([
                'id'             => (string) Str::uuid(),
                'raw_contact_id' => $rawContactId,  // pakai raw_contact_id
                'journey_label'  => 'Stat_New',
                'created_at'     => now(),
            ]);

            return response()->json([
                'status'  => 'created',
                'message' => 'Contact berhasil ditambahkan.'
            ], 201);
        }

        //  4. Kalau sudah ada, update nama & channel saja 
        DB::table('raw_contact')
            ->where('phone', $phone)
            ->update([
                'name'    => $name,
                'channel' => $channelName,
            ]);

        return response()->json([
            'status'  => 'updated',
            'message' => 'Data contact berhasil diperbarui.'
        ], 200);
    }

    public function chat(Request $request)
    {
        $data = $request->all();

        //  1. Abaikan pesan keluar dari bot/agent 
        $isFromMe = $data['fromMe'] ?? false;
        if ($isFromMe) {
            return response()->json(['status' => 'ignored', 'message' => 'Pesan keluar diabaikan.']);
        }

        $phone     = $data['from']      ?? null;
        $text      = $data['text']      ?? '';
        $msgType   = $data['msgType']   ?? 'text';
        $chatId    = $data['chatId']    ?? null;
        $serviceId = $data['serviceId'] ?? null;
        $agentId   = $data['agentId']   ?? null;
        $username  = $data['username']  ?? null;

        //  2. Cari contact berdasarkan nomor HP 
        $contact = DB::table('raw_contact')->where('phone', $phone)->first();
        $rawContactId = $contact ? $contact->id : null;

        //  3. Simpan ke raw_chat
        DB::table('raw_chat')->insert([
            'id'              => (string) Str::uuid(),
            'raw_customer_id' => null,
            'maxchat_msg_id'  => $data['id']  ?? null,
            'chat_id'         => $chatId,
            'service_id'      => $serviceId,
            'agent_id'        => $agentId,
            'phone'           => $phone,
            'username'        => $username,
            'direction'       => 'in',
            'msg_type'        => $msgType,
            'message'         => $text,
            'raw_payload'     => json_encode($data),
            'created_at'      => now(),
        ]);

        // 4. Cek keyword/intent (hanya kalau contact ditemukan)
        if ($rawContactId && $text !== '') {

            // Ambil semua keyword yang masih aktif
            $keywords = DB::table('raw_keyword')
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now()->toDateString());
                })
                ->get();

            $matched = false;
            foreach ($keywords as $kw) {
                if (stripos($text, $kw->keyword) !== false) {
                    $matched = true;
                    break;
                }
            }

            // 5. Kalau cocok → insert Stat_Leads 
            if ($matched) {
                DB::table('raw_cus_journey')->insert([
                    'id'             => (string) Str::uuid(),
                    'raw_contact_id' => $rawContactId,
                    'journey_label'  => 'Stat_Leads',
                    'created_at'     => now(),
                ]);
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }
}