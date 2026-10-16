<?php

namespace Shapperize\Activation\Contracts;

/**
 * Decides whether a gated step is available to a subject: usually a plan
 * entitlement or a feature flag. Unavailable steps are shown locked with an
 * upgrade path, and never block activation.
 */
interface StepGate
{
    public function allows(Activatable $subject, string $gate): bool;
}
