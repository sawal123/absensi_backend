<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    use BelongsToTenant;

    public const TYPE_CHECK_IN = 'check_in';

    public const TYPE_CHECK_OUT = 'check_out';

    public const TYPE_BOTH = 'both';

    public const TYPES = [self::TYPE_CHECK_IN, self::TYPE_CHECK_OUT, self::TYPE_BOTH];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code',
        'employee_id',
        'attendance_id',
        'date',
        'scheduled_start',
        'scheduled_end',
        'recorded_check_in',
        'recorded_check_out',
        'proposed_check_in',
        'proposed_check_out',
        'correction_type',
        'reason',
        'evidence_url',
        'evidence_name',
        'evidence_meta',
        'status',
        'reviewer_id',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recorded_check_in' => 'datetime',
            'recorded_check_out' => 'datetime',
            'proposed_check_in' => 'datetime',
            'proposed_check_out' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
