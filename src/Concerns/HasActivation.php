<?php

namespace Shapperize\Activation\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Shapperize\Activation\Config;
use Shapperize\Activation\Models\TourProgress;

/**
 * For the subject model: reads the activation timestamps from whichever
 * columns are configured, and relates the subject to its tour progress.
 *
 * @mixin Model
 */
trait HasActivation
{
    public function initializeHasActivation(): void
    {
        $this->mergeCasts([
            Config::completedAtColumn() => 'datetime',
            Config::celebratedAtColumn() => 'datetime',
        ]);
    }

    public function activationKey(): string
    {
        $key = $this->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    public function activationCompletedAt(): ?Carbon
    {
        $value = $this->getAttribute(Config::completedAtColumn());

        return $value instanceof Carbon ? $value : null;
    }

    public function activationCelebratedAt(): ?Carbon
    {
        $value = $this->getAttribute(Config::celebratedAtColumn());

        return $value instanceof Carbon ? $value : null;
    }

    public function isActivated(): bool
    {
        return $this->getAttribute(Config::completedAtColumn()) !== null;
    }

    /** @return HasMany<TourProgress, $this> */
    public function tourProgress(): HasMany
    {
        return $this->hasMany(TourProgress::class, Config::foreignKey());
    }
}
