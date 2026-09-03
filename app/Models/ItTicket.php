<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu tiket IT Request Tracker (docs/rencana/rencana-it-tracker.md §14.2).
 *
 * ⚠️ prioritas_pemohon dan prioritas_it DUA kolom terpisah — jangan disatukan.
 * ⚠️ "Berapa hari menunggu" TIDAK pernah disimpan — dihitung dari
 *    it_ticket_fase.masuk_at lewat PhaseEngine::waitOf().
 */
class ItTicket extends Model
{
    protected $table = 'it_ticket';

    protected $fillable = [
        'nomor', 'judul', 'sumber', 'pemohon_user_id', 'raw_branch_id',
        'jenis_permintaan', 'jenjang', 'bobot', 'prioritas_pemohon',
        'prioritas_it', 'pic_user_id', 'fase', 'fase_since', 'induk_id',
        'evaluator_user_id', 'alasan_parkir', 'lahir_dari_id', 'lahir_via',
        'seed_urut',
    ];

    protected $casts = [
        'fase_since' => 'datetime',
        'bobot'      => 'integer',
    ];

    public function pemohon()
    {
        return $this->belongsTo(Users::class, 'pemohon_user_id');
    }

    public function pic()
    {
        return $this->belongsTo(Users::class, 'pic_user_id');
    }

    /** Evaluator FRM-IT-07 — NULL berarti pemohon (default, bisa dialihkan). */
    public function evaluator()
    {
        return $this->belongsTo(Users::class, 'evaluator_user_id');
    }

    /** Tiket asal kalau tiket ini LAHIR dari catatan UAT / RTL evaluasi. */
    public function lahirDari()
    {
        return $this->belongsTo(self::class, 'lahir_dari_id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id');
    }

    public function riwayatFase()
    {
        return $this->hasMany(ItTicketFase::class, 'ticket_id');
    }


    /**
     * Jejak git tiket ini (Kelas B) — diisi `it:sync-git`, dicocokkan lewat
     * NOMOR tiket yang ditulis di commit message, bukan foreign key.
     */
    public function gitRefs()
    {
        return $this->hasMany(ItGitRef::class, 'ticket_nomor', 'nomor')->orderByDesc('commit_at');
    }

    public function forms()
    {
        return $this->hasMany(ItForm::class, 'ticket_id');
    }

    public function frs()
    {
        return $this->hasMany(ItFr::class, 'ticket_id')->orderBy('urutan');
    }

    public function testCases()
    {
        return $this->hasMany(ItTestCase::class, 'ticket_id');
    }

    public function temuan()
    {
        return $this->hasMany(ItTemuan::class, 'ticket_id');
    }

    public function anak()
    {
        return $this->hasMany(self::class, 'induk_id');
    }

    public function induk()
    {
        return $this->belongsTo(self::class, 'induk_id');
    }
}
