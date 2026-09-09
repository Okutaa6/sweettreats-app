<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak git per tiket (FRM-IT-03, Kelas B) — diisi `php artisan it:sync-git`,
 * dibaca layar. Layar TIDAK pernah memanggil git sendiri (rencana §14.3).
 */
class ItGitRef extends Model
{
    protected $table = 'it_git_ref';

    public const UPDATED_AT = null;

    protected $fillable = ['ticket_nomor', 'branch', 'commit_sha', 'subject', 'commit_at'];

    protected $casts = ['commit_at' => 'datetime'];

    /** Berkas SQL/migration yang disebut subject — dipisah supaya bisa disorot. */
    public function scriptDisebut(): array
    {
        preg_match_all('/[\w\/\.-]+\.(?:sql|php)\b/i', (string) $this->subject, $m);
        return $m[0];
    }
}
