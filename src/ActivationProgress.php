<?php

namespace Shapperize\Activation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Contracts\ActivationStep;
use Shapperize\Activation\Contracts\StepGate;
use Shapperize\Activation\Enums\TourStatus;
use Shapperize\Activation\Events\ActivationRegressed;
use Shapperize\Activation\Events\SubjectActivated;
use Shapperize\Activation\Models\TourProgress;
use Shapperize\Activation\Support\StepRegistry;

class ActivationProgress
{
    /** @var array<string, Collection<int, TourProgress>> */
    private array $progressCache = [];

    public function __construct(
        private readonly StepRegistry $registry,
        private readonly StepGate $gate,
        private readonly Dispatcher $events,
    ) {}

    /**
     * The full activation payload for a subject and viewer.
     *
     * @return array{
     *     complete: bool,
     *     celebrate: bool,
     *     percentage: int,
     *     completedCount: int,
     *     requiredCount: int,
     *     steps: array<int, array<string, mixed>>,
     *     nextStep: array<string, mixed>|null,
     *     tours: array<int, array{tourKey: string, status: string, stepIndex: int}>,
     *     dismissedHints: array<int, string>
     * }
     */
    public function forSubject(Activatable&Model $subject, Authenticatable $user): array
    {
        $steps = $this->steps($subject);
        $required = array_values(array_filter($steps, fn (array $step): bool => $step['required']));
        $completedRequired = array_values(array_filter($required, fn (array $step): bool => $step['completed']));
        $complete = $required !== [] && count($completedRequired) === count($required);

        // Stamped here rather than only on a hub page, so finishing the last
        // step anywhere in the app fires the reward straight away. The step set
        // is already cached, so this only writes on an actual transition.
        $this->syncCompletion($subject);

        return [
            'complete' => $complete,
            'celebrate' => $complete && $subject->getAttribute(Config::celebratedAtColumn()) === null,
            'percentage' => $required === [] ? 100 : (int) round(count($completedRequired) / count($required) * 100),
            'completedCount' => count($completedRequired),
            'requiredCount' => count($required),
            'steps' => $steps,
            'nextStep' => $this->nextStep($steps),
            'tours' => $this->tours($subject, $user),
            'dismissedHints' => $this->dismissedHints($subject, $user),
        ];
    }

    /**
     * The minimal payload for an already-activated subject: tour position
     * only, skipping every completion query.
     *
     * @return array{
     *     complete: bool,
     *     celebrate: bool,
     *     percentage: int,
     *     completedCount: int,
     *     requiredCount: int,
     *     steps: array<int, array<string, mixed>>,
     *     nextStep: null,
     *     tours: array<int, array{tourKey: string, status: string, stepIndex: int}>,
     *     dismissedHints: array<int, string>
     * }
     */
    public function forActivatedSubject(Activatable&Model $subject, Authenticatable $user): array
    {
        $requiredCount = count($this->registry->required());

        return [
            'complete' => true,
            'celebrate' => $subject->getAttribute(Config::celebratedAtColumn()) === null,
            'percentage' => 100,
            'completedCount' => $requiredCount,
            'requiredCount' => $requiredCount,
            'steps' => [],
            'nextStep' => null,
            'tours' => $this->tours($subject, $user),
            'dismissedHints' => $this->dismissedHints($subject, $user),
        ];
    }

    /**
     * Every step with its completion and lock state.
     *
     * Cached briefly because it typically runs on each response for a subject
     * that has not activated yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function steps(Activatable&Model $subject): array
    {
        /** @var array<int, array<string, mixed>> $steps */
        $steps = Cache::remember(
            self::cacheKey($subject),
            Config::cacheTtl(),
            fn (): array => $this->computeSteps($subject),
        );

        return $steps;
    }

    /** @return array<int, array<string, mixed>> */
    private function computeSteps(Activatable&Model $subject): array
    {
        return array_map(function (ActivationStep $step) use ($subject): array {
            $gate = $step->gate();
            $available = $gate === null || $this->gate->allows($subject, $gate);

            return [
                'key' => $step->key(),
                'label' => $step->label(),
                'aha' => $step->aha(),
                'href' => $step->href($subject),
                'anchorId' => $step->anchorId(),
                'required' => $step->isRequired(),
                'completed' => $step->isCompletedBy($subject),
                'available' => $available,
                // Locked steps stay visible so the upgrade path is discoverable,
                // but they never hold activation back.
                'locked' => ! $available,
            ];
        }, $this->registry->all());
    }

    /**
     * The single best thing to do next: the first incomplete, unlocked
     * required step, then the first incomplete, unlocked step of any kind.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<string, mixed>|null
     */
    private function nextStep(array $steps): ?array
    {
        foreach ($steps as $step) {
            if (! $step['completed'] && ! $step['locked'] && $step['required']) {
                return $step;
            }
        }

        foreach ($steps as $step) {
            if (! $step['completed'] && ! $step['locked']) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Marks the subject activated once every required step is done, and
     * reopens activation if one stops being true. Returns true when this call
     * is what activated it.
     */
    public function syncCompletion(Activatable&Model $subject): bool
    {
        $steps = $this->steps($subject);
        $required = array_filter($steps, fn (array $step): bool => $step['required']);
        $allDone = $required !== [] && array_filter($required, fn (array $step): bool => ! $step['completed']) === [];
        $column = Config::completedAtColumn();

        if ($allDone && $subject->getAttribute($column) === null) {
            $subject->forceFill([$column => Date::now()])->save();
            $this->events->dispatch(new SubjectActivated($subject));

            return true;
        }

        // Activation can regress (a channel is disabled, a page unpublished);
        // reopening it is more honest than leaving a stale badge.
        if (! $allDone && $subject->getAttribute($column) !== null) {
            $subject->forceFill([$column => null])->save();
            $this->events->dispatch(new ActivationRegressed($subject));
        }

        return false;
    }

    /**
     * Acknowledges the activation reward, once. Returns true when this call
     * recorded it.
     */
    public function celebrate(Activatable&Model $subject): bool
    {
        $celebrated = Config::celebratedAtColumn();

        if ($subject->getAttribute(Config::completedAtColumn()) === null || $subject->getAttribute($celebrated) !== null) {
            return false;
        }

        $subject->forceFill([$celebrated => Date::now()])->save();

        return true;
    }

    /** Records or advances a person's position in a tour. */
    public function recordTour(Activatable&Model $subject, Authenticatable $user, string $tourKey, TourStatus $status, int $stepIndex): TourProgress
    {
        unset($this->progressCache[$this->progressCacheKey($subject, $user)]);

        return TourProgress::query()->updateOrCreate(
            [Config::foreignKey() => $subject->activationKey(), 'user_id' => $user->getAuthIdentifier(), 'tour_key' => $tourKey],
            [
                'status' => $status,
                'step_index' => $stepIndex,
                'last_seen_at' => Date::now(),
                'completed_at' => $status === TourStatus::Completed ? Date::now() : null,
            ],
        );
    }

    public function dismissHint(Activatable&Model $subject, Authenticatable $user, string $hintId): void
    {
        $this->recordTour($subject, $user, TourProgress::hintKey($hintId), TourStatus::Completed, 0);
    }

    /** @return array<int, array{tourKey: string, status: string, stepIndex: int}> */
    private function tours(Activatable&Model $subject, Authenticatable $user): array
    {
        return $this->progressFor($subject, $user)
            ->reject(fn (TourProgress $progress): bool => $progress->isHint())
            ->map(fn (TourProgress $progress): array => [
                'tourKey' => $progress->tour_key,
                'status' => $progress->status->value,
                'stepIndex' => $progress->step_index,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function dismissedHints(Activatable&Model $subject, Authenticatable $user): array
    {
        return $this->progressFor($subject, $user)
            ->filter(fn (TourProgress $progress): bool => $progress->isHint())
            ->map(fn (TourProgress $progress): string => substr($progress->tour_key, strlen(TourProgress::HINT_PREFIX)))
            ->values()
            ->all();
    }

    /**
     * Tour rows for this viewer, fetched once per instance. Both the tour list
     * and the dismissed-hint list are derived from the same handful of rows.
     *
     * @return Collection<int, TourProgress>
     */
    private function progressFor(Activatable&Model $subject, Authenticatable $user): Collection
    {
        return $this->progressCache[$this->progressCacheKey($subject, $user)] ??= TourProgress::query()
            ->where(Config::foreignKey(), $subject->activationKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->get();
    }

    private function progressCacheKey(Activatable $subject, Authenticatable $user): string
    {
        $userKey = $user->getAuthIdentifier();

        return $subject->activationKey().':'.(is_scalar($userKey) ? $userKey : '');
    }

    public static function cacheKey(Activatable|string $subject): string
    {
        $key = $subject instanceof Activatable ? $subject->activationKey() : $subject;

        return Config::cachePrefix().':'.$key;
    }

    /** Drops the cached step set, so the next read recomputes it. */
    public static function invalidate(Activatable|string $subject): void
    {
        Cache::forget(self::cacheKey($subject));
    }
}
