<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware autentikasi Open API vendor (Cekat dst).
 *
 * Dipasang via alias `api.client:<env>` di routes/cekat.php — parameter <env>
 * adalah environment yang diharapkan route ('live' atau 'sandbox'). Token vendor
 * dikirim di header Authorization: Bearer <token>; yang disimpan di tabel
 * `api_clients` adalah hash sha256-nya (plaintext tidak pernah disentuh DB).
 *
 * Aturan kunci "mode auth = mode data":
 *  - Token environment=sandbox HANYA boleh dipakai di prefix /apicekatdev/*
 *  - Token environment=live    HANYA boleh dipakai di prefix /apicekat/*
 * Jadi vendor tidak bisa "salah ketik URL" lalu nulis ke data klinik asli.
 *
 * Setelah lolos, middleware menanam attribute `cekat_sandbox` (bool) di
 * request — ApiCekatController membaca atribut ini di boot() untuk memilih
 * tabel write-sink (`raw_customer` vs `raw_customer_dev` dst).
 */
class VerifyApiClientToken
{
    public function handle(Request $request, Closure $next, string $expectedEnv = 'live'): Response
    {
        if (! in_array($expectedEnv, ['live', 'sandbox'], true)) {
            return $this->fail('SERVER_ERROR', 500, 'Route environment tidak valid.');
        }

        $authHeader = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
            return $this->fail('UNAUTHORIZED', 401, 'Header Authorization Bearer tidak ada atau salah format.');
        }
        $token = $m[1];

        $client = DB::table('api_clients')
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', 1)
            ->first();

        if (! $client) {
            return $this->fail('UNAUTHORIZED', 401, 'Token tidak dikenali atau sudah dinonaktifkan.');
        }

        if ($client->environment !== $expectedEnv) {
            return $this->fail('UNAUTHORIZED', 401, 'Token tidak berlaku untuk environment ini.');
        }

        try {
            DB::table('api_clients')
                ->where('id', $client->id)
                ->update(['last_used_at' => now()]);
        } catch (\Throwable $e) {
            // best-effort, jangan blokir request kalau gagal update timestamp
        }

        $request->attributes->set('cekat_sandbox', $client->environment === 'sandbox');
        $request->attributes->set('cekat_client_id', $client->id);
        $request->attributes->set('cekat_client_name', $client->name);

        return $next($request);
    }

    private function fail(string $code, int $status, string $message): Response
    {
        return response()->json([
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
        ], $status);
    }
}
