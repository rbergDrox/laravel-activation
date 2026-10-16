<?php

namespace Shapperize\Activation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shapperize\Activation\Concerns\HasActivation;
use Shapperize\Activation\Contracts\Activatable;

class Workspace extends Model implements Activatable
{
    use HasActivation;

    protected $guarded = [];

    /** @return HasMany<Widget, $this> */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    public function hasWidget(string $kind): bool
    {
        return $this->widgets()->where('kind', $kind)->exists();
    }
}
