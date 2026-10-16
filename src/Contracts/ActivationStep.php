<?php

namespace Shapperize\Activation\Contracts;

/**
 * One activation step, defined by the moment the product visibly works rather
 * than by a form being filled. Backed enums make good implementations.
 *
 * @template TSubject of Activatable
 */
interface ActivationStep
{
    /** Stable identifier, shared with the frontend. */
    public function key(): string;

    public function label(): string;

    /** What the user will see happen once this step is done. */
    public function aha(): string;

    /** The tour anchor this step's guidance points at. */
    public function anchorId(): ?string;

    /** @param  TSubject  $subject */
    public function href(Activatable $subject): string;

    /** Whether the subject must finish this step to count as activated. */
    public function isRequired(): bool;

    /** The gate this step sits behind, or null when it is always available. */
    public function gate(): ?string;

    /**
     * Whether the subject has reached this step's proof-of-value moment.
     *
     * Prefer existence queries: this runs for every step on every uncached
     * request, and nothing needs hydrating to answer "has one arrived?".
     *
     * @param  TSubject  $subject
     */
    public function isCompletedBy(Activatable $subject): bool;
}
