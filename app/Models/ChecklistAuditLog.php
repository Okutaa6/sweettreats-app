<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit log untuk modul checklist akreditasi. Setiap state
 * transition penting (create/update/submit/approve/reject/reopen) wajib
 * meninggalkan entry di sini.
 *
 * Pakai static `write()` supaya call-site di controller ringkas dan
 * konsisten (actor & branch otomatis dari session).
 */
class ChecklistAuditLog extends Model
{
    use HasUuids;

    protected $table = 'sys_checklist_audit_log';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'actor_user_id', 'raw_branch_id', 'target_type', 'target_id',
        'action', 'before_json', 'after_json', 'notes', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    const ACTION_CREATE  = 'create';
    const ACTION_UPDATE  = 'update';
    const ACTION_DELETE  = 'delete';
    const ACTION_SUBMIT  = 'submit';
    const ACTION_APPROVE = 'approve';
    const ACTION_REJECT  = 'reject';
    const ACTION_REOPEN  = 'reopen';
    const ACTION_LOCK    = 'lock';
    const ACTION_EXCUSE  = 'excuse';

    public static function actionOptions(): array
    {
        return [
            self::ACTION_CREATE  => ['label' => 'Create',  'color' => 'info'],
            self::ACTION_UPDATE  => ['label' => 'Update',  'color' => 'info'],
            self::ACTION_DELETE  => ['label' => 'Delete',  'color' => 'warning'],
            self::ACTION_SUBMIT  => ['label' => 'Submit',  'color' => 'success'],
            self::ACTION_APPROVE => ['label' => 'Approve', 'color' => 'success'],
            self::ACTION_REJECT  => ['label' => 'Reject',  'color' => 'danger'],
            self::ACTION_REOPEN  => ['label' => 'Reopen',  'color' => 'warning'],
            self::ACTION_LOCK    => ['label' => 'Lock',    'color' => 'neutral'],
            self::ACTION_EXCUSE  => ['label' => 'Dikecualikan', 'color' => 'neutral'],
        ];
    }

    /**
     * Tulis 1 entry audit. Actor & branch otomatis dari session.
     * $before / $after diharap array kecil (subset field, bukan seluruh row).
     */
    public static function write(
        string $targetType,
        string $targetId,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?string $notes = null
    ): self {
        return self::create([
            'actor_user_id' => session('id'),
            'raw_branch_id' => session('raw_branch_id'),
            'target_type'   => $targetType,
            'target_id'     => $targetId,
            'action'        => $action,
            'before_json'   => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_json'    => $after  ? json_encode($after,  JSON_UNESCAPED_UNICODE) : null,
            'notes'         => $notes,
            'created_at'    => now(),
        ]);
    }

    public function actor()
    {
        return $this->belongsTo(Users::class, 'actor_user_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function actionLabel(): string
    {
        return self::actionOptions()[$this->action]['label'] ?? $this->action;
    }

    public function actionColor(): string
    {
        return self::actionOptions()[$this->action]['color'] ?? 'neutral';
    }
}
