<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaxChatWebhookController extends Controller
{
    public function contact(Request $request)
    {
        // ── 1. Validasi payload ──────────────────────────────────────────────
        $validated = $request->validate([
            'id'                   => 'required|string',
            'name'                 => 'required|string',
            'channel'              => 'required|array|min:1',
            'channel.*.name'       => 'required|string',
            'channel.*.identifier' => 'required|string',
            'domain'               => 'required|string',
            'eventType'            => 'required|string',
        ]);

        // Ambil data channel pertama
        $channel     = collect($validated['channel'])->first();
        $phone       = $channel['identifier'];
        $channelName = $channel['name'];
        $name        = $validated['name'];

        // ── 2. Cek nomor di raw_customer ─────────────────────────────────────
        $existing = DB::table('raw_customer')
            ->where('phone', $phone)
            ->first();

        if (! $existing) {
            // ── 3a. INSERT raw_customer ──────────────────────────────────────
            $rawCustomerId = (string) Str::uuid();

            DB::table('raw_customer')->insert([
                'id'         => $rawCustomerId,
                'source_app' => 'maxchat',
                'name'       => $name,
                'phone'      => $phone,
                'channel'    => $channelName,
            ]);

            // ── 3b. INSERT raw_cus_journey → Stat_New (bukan Stat_Leads) ─────
            DB::table('raw_cus_journey')->insert([
                'id'              => (string) Str::uuid(),
                'raw_customer_id' => $rawCustomerId,
                'journey_label'   => 'Stat_New',
                'created_at'      => now(),
            ]);

            return response()->json([
                'status'  => 'created',
                'message' => 'Customer berhasil ditambahkan.'
            ], 201);
        }

        // ── 4. UPDATE jika nomor sudah ada ───────────────────────────────────
        DB::table('raw_customer')
            ->where('phone', $phone)
            ->update([
                'name'    => $name,
                'channel' => $channelName,
            ]);

        return response()->json([
            'status'  => 'updated',
            'message' => 'Data customer berhasil diperbarui.'
        ], 200);
    }

    public function chat(Request $request)
    {
        $data = $request->all();

        // ── 1. Hanya proses pesan MASUK dari customer (bukan dari bot/agent) ─
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

        // ── 2. Cari customer berdasarkan nomor HP ────────────────────────────
        $customer = DB::table('raw_customer')->where('phone', $phone)->first();

        $rawCustomerId = $customer ? $customer->id : null;

        // ── 3. Simpan ke raw_chat ────────────────────────────────────────────
        DB::table('raw_chat')->insert([
            'id'              => (string) Str::uuid(),
            'raw_customer_id' => $rawCustomerId,
            'maxchat_msg_id'  => $data['id']        ?? null,
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

        // ── 4. Cek keyword/intent (hanya kalau customer ditemukan) ──────────
        if ($rawCustomerId && $text !== '') {

            // Ambil semua keyword yang masih aktif
            // aktif = end_date NULL atau end_date >= hari ini
            $keywords = DB::table('raw_keyword')
                ->where(function ($q) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', now()->toDateString());
                })
                ->get();

            $matched = false;
            foreach ($keywords as $kw) {
                // Case-insensitive matching
                if (stripos($text, $kw->keyword) !== false) {
                    $matched = true;
                    break;
                }
            }

            // ── 5. Kalau ada keyword yang cocok → insert Stat_Leads 
            if ($matched) {
                DB::table('raw_cus_journey')->insert([
                    'id'              => (string) Str::uuid(),
                    'raw_customer_id' => $rawCustomerId,
                    'journey_label'   => 'Stat_Leads',
                    'created_at'      => now(),
                ]);
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }
}