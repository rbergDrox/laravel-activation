<?php

namespace Shapperize\Activation\Tests\Fixtures;

use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Contracts\ActivationStep;

/** @implements ActivationStep<Workspace> */
enum FixtureStep: string implements ActivationStep
{
    case Profile = 'profile';
    case Channels = 'channels';
    case Publish = 'publish';
    case Team = 'team';

    public function key(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function aha(): string
    {
        return 'You see '.$this->value.' working.';
    }

    public function anchorId(): string
    {
        return 'nav.'.$this->value;
    }

    public function href(Activatable $subject): string
    {
        return '/workspaces/'.$subject->activationKey().'/'.$this->value;
    }

    public function isRequired(): bool
    {
        return $this === self::Profile || $this === self::Publish;
    }

    public function gate(): ?string
    {
        return $this === self::Channels ? 'channels' : null;
    }

    public function isCompletedBy(Activatable $subject): bool
    {
        return $subject->hasWidget($this->value);
    }
}
