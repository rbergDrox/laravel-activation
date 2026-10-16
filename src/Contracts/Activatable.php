<?php

namespace Shapperize\Activation\Contracts;

/**
 * The thing being activated: a workspace, team or account.
 *
 * Activation belongs to the subject rather than to whoever finished the last
 * step, so a teammate who joins later sees an activated product.
 */
interface Activatable
{
    /** The value stored in the configured foreign key column. */
    public function activationKey(): string;
}
