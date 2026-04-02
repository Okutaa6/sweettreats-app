<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawBranchTarget extends Model
{
    protected $table = 'raw_branch_target';

    protected $fillable = [
        'cabang_id',
        'tahun',
        'bulan',
        'nominal',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
        'tahun'   => 'integer',
        'bulan'   => 'integer',
    ];

    // Nama bulan untuk display
    public static function namaBulan(): array
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'cabang_id', 'id');
    }
}