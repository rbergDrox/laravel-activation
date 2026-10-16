<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Shapperize\Activation\ActivationProgress;
use Shapperize\Activation\Contracts\StepGate;
use Shapperize\Activation\Enums\TourStatus;
use Shapperize\Activation\Events\ActivationRegressed;
use Shapperize\Activation\Events\SubjectActivated;
use Shapperize\Activation\Tests\Fixtures\ListGate;
use Shapperize\Activation\Tests\Fixtures\Widget;

function progress(): ActivationProgress
{
    return app(ActivationProgress::class);
}

test('every step starts incomplete for a fresh subject', function () {
    $payload = progress()->forSubject(workspace(), user());

    expect($payload['steps'])->toHaveCount(4)
        ->and(collect($payload['steps'])->pluck('completed')->unique()->all())->toBe([false])
        ->and($payload['complete'])->toBeFalse()
        ->and($payload['percentage'])->toBe(0)
        ->and($payload['completedCount'])->toBe(0)
        ->and($payload['requiredCount'])->toBe(2);
});

test('each step row carries the shape the frontend reads', function () {
    $workspace = workspace();

    expect(progress()->steps($workspace)[0])->toBe([
        'key' => 'profile',
        'label' => 'Profile',
        'aha' => 'You see profile working.',
        'href' => '/workspaces/'.$workspace->id.'/profile',
        'anchorId' => 'nav.profile',
        'required' => true,
        'completed' => false,
        'available' => true,
        'locked' => false,
    ]);
});

test('the gate decides whether a step is locked', function () {
    app()->instance(StepGate::class, new ListGate);

    $channels = collect(progress()->steps(workspace()))->firstWhere('key', 'channels');
    expect($channels['locked'])->toBeTrue()->and($channels['available'])->toBeFalse();

    app()->instance(StepGate::class, new ListGate(['channels']));

    $channels = collect(progress()->steps(workspace()))->firstWhere('key', 'channels');
    expect($channels['locked'])->toBeFalse();
});

test('a locked step never blocks activation', function () {
    app()->instance(StepGate::class, new ListGate);
    $workspace = complete(workspace(), 'profile', 'publish');

    $payload = progress()->forSubject($workspace, user());

    expect($payload['complete'])->toBeTrue()
        ->and($payload['celebrate'])->toBeTrue()
        ->and($workspace->fresh()->activationCompletedAt())->not->toBeNull();
});

test('activation is stamped once every required step is done, and reopens if one regresses', function () {
    Event::fake([SubjectActivated::class, ActivationRegressed::class]);
    $workspace = complete(workspace(), 'profile', 'publish');

    expect(progress()->syncCompletion($workspace))->toBeTrue()
        ->and($workspace->fresh()->isActivated())->toBeTrue();
    Event::assertDispatchedTimes(SubjectActivated::class, 1);

    // A second sync is not a new activation.
    expect(progress()->syncCompletion($workspace))->toBeFalse();
    Event::assertDispatchedTimes(SubjectActivated::class, 1);

    $workspace->widgets()->where('kind', 'publish')->delete();
    ActivationProgress::invalidate($workspace);

    progress()->syncCompletion($workspace->fresh());

    expect($workspace->fresh()->isActivated())->toBeFalse();
    Event::assertDispatched(ActivationRegressed::class, fn (ActivationRegressed $event) => $event->subject->is($workspace));
});

test('the next step is required before optional, and skips locked steps', function () {
    app()->instance(StepGate::class, new ListGate);
    $workspace = workspace();

    expect(progress()->forSubject($workspace, user())['nextStep']['key'])->toBe('profile');

    complete($workspace, 'profile');
    expect(progress()->forSubject($workspace, user())['nextStep']['key'])->toBe('publish');

    // Channels comes first in order but is locked, so the optional fallback is team.
    complete($workspace, 'publish');
    expect(progress()->forSubject($workspace, user())['nextStep']['key'])->toBe('team');

    complete($workspace, 'team');
    expect(progress()->forSubject($workspace, user())['nextStep'])->toBeNull();
});

test('a write to a watched model invalidates the cached step set', function () {
    $workspace = workspace();

    expect(progress()->steps($workspace)[0]['completed'])->toBeFalse();

    Widget::query()->create(['workspace_id' => $workspace->id, 'kind' => 'profile']);

    expect(progress()->steps($workspace)[0]['completed'])->toBeTrue();

    Widget::query()->where('kind', 'profile')->first()->delete();

    expect(progress()->steps($workspace)[0]['completed'])->toBeFalse();
});

test('the step set is cached between watched writes', function () {
    $workspace = workspace();
    progress()->steps($workspace);

    DB::enableQueryLog();
    progress()->steps($workspace);

    expect(DB::getQueryLog())->toBe([]);
});

test('the activated subject fast path runs no completion queries', function () {
    $workspace = complete(workspace(), 'profile', 'publish');
    progress()->syncCompletion($workspace);
    ActivationProgress::invalidate($workspace);
    $user = user();

    DB::enableQueryLog();
    $payload = progress()->forActivatedSubject($workspace->fresh(), $user);
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($payload['complete'])->toBeTrue()
        ->and($payload['steps'])->toBe([])
        ->and($payload['percentage'])->toBe(100)
        ->and($payload['requiredCount'])->toBe(2)
        ->and($queries->filter(fn (string $sql) => str_contains($sql, 'widgets'))->all())->toBe([]);
});

test('tour progress is stored per user and per subject and is upserted', function () {
    $workspace = workspace();
    $ada = user();
    $grace = user();

    progress()->recordTour($workspace, $ada, 'welcome', TourStatus::Skipped, 3);
    progress()->recordTour($workspace, $ada, 'welcome', TourStatus::InProgress, 4);
    progress()->recordTour(workspace(), $ada, 'welcome', TourStatus::Completed, 5);

    expect(progress()->forSubject($workspace, $ada)['tours'])->toBe([
        ['tourKey' => 'welcome', 'status' => 'in_progress', 'stepIndex' => 4],
    ])->and(progress()->forSubject($workspace, $grace)['tours'])->toBe([]);
});

test('a recorded tour shows on the same instance straight away', function () {
    $workspace = workspace();
    $ada = user();
    $service = progress();

    expect($service->forSubject($workspace, $ada)['tours'])->toBe([]);

    $service->recordTour($workspace, $ada, 'welcome', TourStatus::Completed, 2);

    expect($service->forSubject($workspace, $ada)['tours'])->toHaveCount(1);
});

test('a dismissed hint is recorded separately from tours', function () {
    $workspace = workspace();
    $ada = user();

    progress()->dismissHint($workspace, $ada, 'inbox');

    $payload = progress()->forSubject($workspace, $ada);

    expect($payload['dismissedHints'])->toBe(['inbox'])
        ->and($payload['tours'])->toBe([]);
});

test('the celebration is acknowledged only once, and only after activation', function () {
    $workspace = workspace();

    expect(progress()->celebrate($workspace))->toBeFalse();

    complete($workspace, 'profile', 'publish');
    progress()->syncCompletion($workspace);

    expect(progress()->celebrate($workspace))->toBeTrue();
    $celebratedAt = $workspace->fresh()->activationCelebratedAt();

    $this->travel(5)->minutes();

    expect(progress()->celebrate($workspace->fresh()))->toBeFalse()
        ->and($workspace->fresh()->activationCelebratedAt()->timestamp)->toBe($celebratedAt->timestamp)
        ->and(progress()->forActivatedSubject($workspace->fresh(), user())['celebrate'])->toBeFalse();
});
