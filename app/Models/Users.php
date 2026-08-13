<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'nama',
        'email',
        'password',
        'status',
        'last_login',
        'reset_token',
        'register_token',
        'group_id',
        'raw_branch_id', // branch
        'raw_doctor_id', // NULL = akun ini bukan dokter (rencana-akun-dokter.md §3)
        // Pusat Akun Fase B
        'last_seen_at',
        'must_change_password',
        'force_logout_at',
        'created_at', // $timestamps=false → diisi manual saat create (masa tenggang detektor)
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $hidden = ['password'];

    public function group()
    {
        return $this->belongsTo(GroupPath::class, 'group_id', 'id');
    }

    public function approvals()
    {
        return $this->hasMany(UserApproval::class, 'approved_by', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Dokter yang diwakili akun ini — NULL untuk akun karyawan biasa.
     *
     * Ini pintu ke seluruh dunia dokter yang sudah lengkap di database tapi
     * selama ini tak bisa dilihat pemiliknya: jadwal praktik, jadwal khusus,
     * layanan, dan reservasi (rencana-akun-dokter.md §1). Dari sini
     * `->doctor->schedules` / `->doctor->reservations` langsung terpakai.
     */
    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'raw_doctor_id', 'id');
    }

    /** Akun ini mewakili seorang dokter? Dipakai sebagai gerbang layar dokter. */
    public function isDoctor(): bool
    {
        return $this->raw_doctor_id !== null;
    }
}