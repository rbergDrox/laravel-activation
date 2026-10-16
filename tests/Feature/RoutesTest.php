<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Shapperize\Activation\Activation;
use Shapperize\Activation\ActivationProgress;
use Shapperize\Activation\ActivationServiceProvider;
use Shapperize\Activation\Enums\TourStatus;
use Shapperize\Activation\Models\TourProgress;
use Shapperize\Activation\Tests\Fixtures\Workspace;

/** Turns the package routes on and allows every viewer through the default gate. */
function enableRoutes(): void
{
    config()->set('activation.routes.enabled', true);
    config()->set('activation.routes.middleware', ['web']);
    Gate::define('view', fn ($user, Workspace $workspace) => true);

    (new ActivationServiceProvider(app()))->boot();
    Route::getRoutes()->refreshNameLookups();
}

test('routes are disabled by default', function () {
    expect(Route::has('activation.tour'))->toBeFalse();
});

test('a tour is recorded through the route', function () {
    enableRoutes();
    $workspace = workspace();
    $ada = user();

    $this->actingAs($ada)
        ->post("/activation/{$workspace->id}/tour", ['tour_key' => 'welcome', 'status' => 'skipped', 'step_index' => 3])
        ->assertNoContent();

    $this->actingAs($ada)
        ->post("/activation/{$workspace->id}/tour", ['tour_key' => 'welcome', 'status' => 'in_progress', 'step_index' => 4])
        ->assertNoContent();

    $row = TourProgress::query()->sole();

    expect($row->status)->toBe(TourStatus::InProgress)
        ->and($row->step_index)->toBe(4);
});

test('tour input is validated', function () {
    enableRoutes();
    $workspace = workspace();

    $this->actingAs(user())
        ->postJson("/activation/{$workspace->id}/tour", ['tour_key' => '', 'status' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tour_key', 'status']);

    $this->actingAs(user())
        ->postJson("/activation/{$workspace->id}/hints/dismiss", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hint_id']);
});

test('hints are dismissed and the celebration acknowledged through the routes', function () {
    enableRoutes();
    $workspace = complete(workspace(), 'profile', 'publish');
    app(ActivationProgress::class)->syncCompletion($workspace);
    $ada = user();

    $this->actingAs($ada)->post("/activation/{$workspace->id}/hints/dismiss", ['hint_id' => 'inbox'])->assertNoContent();
    $this->actingAs($ada)->post("/activation/{$workspace->id}/celebrate")->assertNoContent();

    expect(app(ActivationProgress::class)->forSubject($workspace->fresh(), $ada)['dismissedHints'])->toBe(['inbox'])
        ->and($workspace->fresh()->activationCelebratedAt())->not->toBeNull();
});

test('the default authorization refuses a viewer the gate denies', function () {
    enableRoutes();
    Gate::define('view', fn ($user, Workspace $workspace) => false);
    $workspace = workspace();

    $this->actingAs(user())
        ->post("/activation/{$workspace->id}/celebrate")
        ->assertForbidden();
});

test('authorization can be replaced', function () {
    enableRoutes();
    Activation::authorizeUsing(fn ($request, $subject) => abort(404));
    $workspace = workspace();

    $this->actingAs(user())
        ->post("/activation/{$workspace->id}/celebrate")
        ->assertNotFound();
});

test('an unknown subject is a 404', function () {
    enableRoutes();

    $this->actingAs(user())
        ->post('/activation/999/celebrate')
        ->assertNotFound();
});
