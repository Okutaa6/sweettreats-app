<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Role = Jabatan. Menentukan hak akses menu (via group_access) DAN posisi di
 * struktur organisasi (hierarki atasan-bawahan via parent_id) untuk approval
 * berjenjang. Satu role menampung banyak user (users.group_id).
 */
class GroupPath extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'group_path';

    protected $fillable = [
        'id',
        'nama',
        'parent_id',
        'parent_id_2',
        'level',
        'deskripsi',
        'landing_page',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public function groupAccesses()
    {
        return $this->hasMany(GroupAccess::class, 'group_id', 'id');
    }

    public function users()
    {
        return $this->hasMany(Users::class, 'group_id', 'id');
    }

    /** Divisi checklist yang ditangani role ini sebagai staf (satu role = satu divisi). */
    public function division()
    {
        return $this->hasOne(ChecklistDivision::class, 'group_id', 'id');
    }

    /* ---------- Hierarki organisasi (adjacency-list) ---------- */

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id')->orderBy('nama');
    }

    /** Rantai atasan ke atas (nearest-first). Pohon kecil → walk in-PHP. */
    public function ancestors(): \Illuminate\Support\Collection
    {
        $chain = collect();
        $node  = $this->parent;
        while ($node) {
            $chain->push($node);
            $node = $node->parent;
        }
        return $chain;
    }

    /** Semua turunan (rekursif). */
    public function descendants(): \Illuminate\Support\Collection
    {
        $out = collect();
        foreach ($this->children as $child) {
            $out->push($child);
            $out = $out->merge($child->descendants());
        }
        return $out;
    }

    /** Id diri sendiri + semua turunan (untuk pembatasan akses subtree). */
    public function subtreeIds(): array
    {
        return $this->descendants()->pluck('id')->prepend($this->id)->all();
    }

    /* ---------- Atasan kedua (khusus supervisi checklist) ---------- */
    // `parent_id` di atas tetap atasan UTAMA & satu-satunya penentu struktur
    // pohon (level, drag-drop, siklus). Yang di bawah ini HANYA dipakai konsumen
    // checklist supaya satu role bisa disupervisi DUA atasan — tak menyentuh
    // logika pohon di atas.

    /** Atasan kedua (opsional). */
    public function parent2()
    {
        return $this->belongsTo(self::class, 'parent_id_2', 'id');
    }

    /** Role yang menjadikan node ini ATASAN KEDUA-nya. */
    public function extraChildren()
    {
        return $this->hasMany(self::class, 'parent_id_2', 'id')->orderBy('nama');
    }

    /**
     * Semua bawahan supervisi (rekursif) lewat atasan utama DAN atasan kedua.
     * BFS dgn visited-set: pohon jadi DAG (parent_id_2 bisa menyilang cabang lain),
     * jadi cegah kunjungan ganda / loop tak berujung. Dipakai untuk menentukan
     * divisi checklist yang boleh di-review sebuah role.
     */
    public function supervisedDescendants(): \Illuminate\Support\Collection
    {
        $out   = collect();
        $seen  = [$this->id => true];
        $queue = [$this];

        while ($queue) {
            $node = array_shift($queue);
            foreach ($node->children->merge($node->extraChildren) as $child) {
                if (isset($seen[$child->id])) continue;
                $seen[$child->id] = true;
                $out->push($child);
                $queue[] = $child;
            }
        }
        return $out;
    }

    /**
     * Semua atasan supervisi (rekursif) lewat atasan utama DAN atasan kedua.
     * BFS dgn visited-set (DAG, lihat supervisedDescendants). Dipakai untuk
     * menargetkan notifikasi checklist ke SEMUA atasan sebuah role.
     */
    public function supervisorAncestors(): \Illuminate\Support\Collection
    {
        $out   = collect();
        $seen  = [$this->id => true];
        $queue = [$this];

        while ($queue) {
            $node = array_shift($queue);
            foreach ([$node->parent, $node->parent2] as $up) {
                if (! $up || isset($seen[$up->id])) continue;
                $seen[$up->id] = true;
                $out->push($up);
                $queue[] = $up;
            }
        }
        return $out;
    }
}
