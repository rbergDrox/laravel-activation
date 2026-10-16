<?php

namespace Shapperize\Activation\Tests\Fixtures;

use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Contracts\StepGate;

class ListGate implements StepGate
{
    /** @param  array<int, string>  $allowed */
    public function __construct(public array $allowed = []) {}

    public function allows(Activatable $subject, string $gate): bool
    {
        return in_array($gate, $this->allowed, true);
    }
}
