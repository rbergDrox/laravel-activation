<?php

namespace Shapperize\Activation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Shapperize\Activation\Config;
use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Enums\TourStatus;

/**
 * One person's position in one tour, for one subject.
 *
 * Dismissed hints share this table under a `hint:` prefixed `tour_key`, since
 * they answer the same question: has this person already seen it?
 *
 * @property string $id
 * @property int|string $user_id
 * @property string $tour_key
 * @property TourStatus $status
 * @property int $step_index
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $completed_at
 */
class TourProgress extends Model
{
    use HasUlids;

    public const HINT_PREFIX = 'hint:';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'not_started',
        'step_index' => 0,
    ];

    public function getTable(): string
    {
        return Config::progressTable();
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Config::userModel(), 'user_id');
    }

    /** @return BelongsTo<Activatable&Model, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Config::subjectModel(), Config::foreignKey());
    }

    public function isHint(): bool
    {
        return str_starts_with($this->tour_key, self::HINT_PREFIX);
    }

    public static function hintKey(string $hintId): string
    {
        return self::HINT_PREFIX.$hintId;
    }

    protected function casts(): array
    {
        return [
            'status' => TourStatus::class,
            'step_index' => 'integer',
            'last_seen_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
