<?php

namespace Shapperize\Activation\Events;

use Illuminate\Database\Eloquent\Model;
use Shapperize\Activation\Contracts\Activatable;

/** Every required step is done, for the first time or again after a regression. */
class SubjectActivated
{
    public function __construct(public readonly Activatable&Model $subject) {}
}
