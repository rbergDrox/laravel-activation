<?php

namespace Shapperize\Activation\Gates;

use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Contracts\StepGate;

/** The default: every gated step is available. Bind your own StepGate to lock them. */
class AllowAllStepGate implements StepGate
{
    public function allows(Activatable $subject, string $gate): bool
    {
        return true;
    }
}
