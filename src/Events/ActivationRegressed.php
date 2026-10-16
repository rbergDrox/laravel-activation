<?php

namespace Shapperize\Activation\Events;

use Illuminate\Database\Eloquent\Model;
use Shapperize\Activation\Contracts\Activatable;

/** A required step stopped being true after the subject had activated. */
class ActivationRegressed
{
    public function __construct(public readonly Activatable&Model $subject) {}
}
