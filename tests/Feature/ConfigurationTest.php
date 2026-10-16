<?php

use Illuminate\Database\Eloquent\Model;
use Shapperize\Activation\Activation;
use Shapperize\Activation\ActivationProgress;
use Shapperize\Activation\Support\StepRegistry;
use Shapperize\Activation\Tests\Fixtures\FixtureStep;
use Shapperize\Activation\Tests\Fixtures\Widget;

test('steps can be configured as a list instead of an enum class', function () {
    config()->set('activation.steps', [FixtureStep::Profile, FixtureStep::Publish]);

    expect(app(StepRegistry::class)->all())->toHaveCount(2);
});

test('an invalid steps value is rejected', function () {
    config()->set('activation.steps', [stdClass::class]);

    app(StepRegistry::class)->all();
})->throws(InvalidArgumentException::class);

test('the cache key uses the configured prefix', function () {
    config()->set('activation.cache.prefix', 'onboarding');

    expect(ActivationProgress::cacheKey('abc'))->toBe('onboarding:abc');
});

test('a custom resolver decides which subject a watched record belongs to', function () {
    $workspace = workspace();
    $other = workspace();
    Activation::resolveSubjectKeyUsing(fn (Model $record) => (string) $other->id);

    app(ActivationProgress::class)->steps($workspace);
    Widget::query()->create(['workspace_id' => $workspace->id, 'kind' => 'profile']);

    // The write was attributed to the other workspace, so this one is still cached.
    expect(app(ActivationProgress::class)->steps($workspace)[0]['completed'])->toBeFalse();
});
