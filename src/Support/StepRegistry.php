<?php

namespace Shapperize\Activation\Support;

use BackedEnum;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Contracts\ActivationStep;

/**
 * Resolves the configured steps: the cases of a backed enum, or a list of step
 * classes built by the container so they can take constructor dependencies.
 */
class StepRegistry
{
    /** @var array<int, ActivationStep<Activatable>>|null */
    private ?array $steps = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<int, ActivationStep<Activatable>> */
    public function all(): array
    {
        return $this->steps ??= $this->resolve(Config::get('activation.steps', []));
    }

    /** @return array<int, ActivationStep<Activatable>> */
    public function required(): array
    {
        return array_values(array_filter($this->all(), fn (ActivationStep $step): bool => $step->isRequired()));
    }

    /** @return array<int, ActivationStep<Activatable>> */
    private function resolve(mixed $configured): array
    {
        if (is_string($configured) && enum_exists($configured) && is_subclass_of($configured, BackedEnum::class)) {
            $configured = $configured::cases();
        }

        if (! is_array($configured)) {
            throw new InvalidArgumentException('activation.steps must be an enum class or a list of '.ActivationStep::class.' classes.');
        }

        return array_values(array_map(function (mixed $step): ActivationStep {
            if (is_string($step)) {
                $step = $this->container->make($step);
            }

            if (! $step instanceof ActivationStep) {
                throw new InvalidArgumentException('Every activation step must implement '.ActivationStep::class.'.');
            }

            /** @var ActivationStep<Activatable> $step */
            return $step;
        }, $configured));
    }
}
