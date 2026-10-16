# shapperize/laravel-activation

"Aha-moment" activation for Laravel SaaS apps.

An onboarding step is not a checkbox. Each step owns:

- a **proof-of-value check**: has the account actually reached the moment where the product worked?
- a **route** and a **tour anchor**, so the UI can take people there
- an optional **plan gate**: gated steps are shown locked with an upgrade path, and never block activation
- whether it is **required** for activation

The package computes progress, the single best next step and activation. It handles **regression**: activation reopens if a required step stops being true. It stores per-user tour position and dismissed hints, and drops its cache automatically when the models behind a step change.

It pairs with [`@shapperize/react-tour`](https://github.com/rbergDrox/react-tour) on the frontend, but either works without the other.

Requires PHP 8.3+ and Laravel 12 or 13.

## Install

```sh
composer require shapperize/laravel-activation
php artisan vendor:publish --tag=activation-config
php artisan vendor:publish --tag=activation-migrations   # skip if your tables already exist
php artisan migrate
```

Set `subject.*` in `config/activation.php` before publishing the migrations: they read the subject table, foreign key, key type (`id`, `ulid` or `uuid`) and column names from it.

## The subject

The subject is whatever gets activated: a workspace, team or account.

```php
use Shapperize\Activation\Concerns\HasActivation;
use Shapperize\Activation\Contracts\Activatable;

class Team extends Model implements Activatable
{
    use HasActivation; // activationKey(), isActivated(), activationCompletedAt(), tourProgress()…
}
```

## Steps

A backed enum is the most ergonomic way to write steps. Order the cases in the order you want people to experience them.

```php
use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Contracts\ActivationStep;

/** @implements ActivationStep<Team> */
enum ActivationSteps: string implements ActivationStep
{
    case FirstProject = 'first_project';
    case InviteTeammate = 'invite_teammate';
    case Integrations = 'integrations';

    public function key(): string { return $this->value; }
    public function label(): string { return __("activation.{$this->value}.label"); }
    public function aha(): string { return __("activation.{$this->value}.aha"); }
    public function anchorId(): ?string { return "nav.{$this->value}"; }
    public function href(Activatable $team): string { return route('projects.index', $team); }
    public function isRequired(): bool { return $this !== self::Integrations; }
    public function gate(): ?string { return $this === self::Integrations ? 'integrations' : null; }

    public function isCompletedBy(Activatable $team): bool
    {
        return match ($this) {
            self::FirstProject => $team->projects()->exists(),
            self::InviteTeammate => $team->members()->count() > 1,
            self::Integrations => $team->integrations()->exists(),
        };
    }
}
```

```php
// config/activation.php
'steps' => App\Activation\ActivationSteps::class,
```

Prefer existence queries in `isCompletedBy`: it runs for every step whenever the cached step set is cold.

Enums cannot take constructor dependencies. Resolve services inside `isCompletedBy`, or configure a list of step classes instead; they are built by the container:

```php
'steps' => [FirstProjectStep::class, InviteTeammateStep::class],
```

The package ships no copy. Labels and aha text come from your steps, so they live in your translation files.

## Gates

By default every gated step is available. Bind a `StepGate` to lock steps behind a plan or a flag. Locked steps are still returned, with `locked: true`, so the upgrade path stays visible. They never block activation.

With Laravel Pennant:

```php
use Laravel\Pennant\Feature;
use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Contracts\StepGate;

class PennantStepGate implements StepGate
{
    public function allows(Activatable $subject, string $gate): bool
    {
        return Feature::for($subject)->active($gate);
    }
}

// AppServiceProvider::register()
$this->app->singleton(StepGate::class, PennantStepGate::class);
```

With your own billing:

```php
class PlanStepGate implements StepGate
{
    public function __construct(private Entitlements $entitlements) {}

    public function allows(Activatable $subject, string $gate): bool
    {
        return $this->entitlements->can($subject, $gate);
    }
}
```

## Reading progress

```php
use Shapperize\Activation\ActivationProgress;

$progress = app(ActivationProgress::class);

$payload = $team->isActivated()
    ? $progress->forActivatedSubject($team, $user)   // no completion queries
    : $progress->forSubject($team, $user);
```

`forSubject` returns:

| Key | |
|---|---|
| `complete` | every required step is done |
| `celebrate` | complete, and the reward has not been acknowledged yet |
| `percentage`, `completedCount`, `requiredCount` | over required steps |
| `steps` | `key, label, aha, href, anchorId, required, completed, available, locked` per step |
| `nextStep` | the first incomplete, unlocked required step; otherwise the first incomplete, unlocked step; otherwise `null` |
| `tours` | `[{ tourKey, status, stepIndex }]` for this user |
| `dismissedHints` | hint ids this user dismissed |

`forSubject` also calls `syncCompletion()`, so finishing the last step anywhere in the app stamps activation on the next response.

Other methods: `steps($subject)`, `syncCompletion($subject)`, `celebrate($subject)`, `recordTour($subject, $user, $tourKey, TourStatus $status, $stepIndex)`, `dismissHint($subject, $user, $hintId)`, and the static `ActivationProgress::invalidate($subjectOrKey)`.

### Sharing it with every Inertia response

The package does not depend on Inertia. The pattern that keeps a shared prop cheap:

```php
// HandleInertiaRequests::share()
'activation' => fn () => $team && $user
    ? ($team->isActivated()
        ? app(ActivationProgress::class)->forActivatedSubject($team, $user)
        : app(ActivationProgress::class)->forSubject($team, $user))
    : null,
```

## Caching

The step set is cached for `cache.ttl` seconds (default 60) under `{cache.prefix}:{subject key}`. List the models a step is derived from under `watch`; saving or deleting one drops the cache for the subject it belongs to, read from `subject.foreign_key`:

```php
'watch' => [App\Models\Project::class, App\Models\Membership::class],
```

For models that reach the subject some other way:

```php
Activation::resolveSubjectKeyUsing(fn (Model $record) => $record->project?->team_id);
```

## Events

| Event | When |
|---|---|
| `SubjectActivated` | every required step became done |
| `ActivationRegressed` | a required step stopped being true after activation |

Both carry `$subject`. Hook analytics, emails or billing nudges here.

## Routes (optional)

```php
'routes' => ['enabled' => true, 'prefix' => 'activation', 'middleware' => ['web', 'auth']],
```

| Route | Body |
|---|---|
| `POST {prefix}/{subject}/tour` (`activation.tour`) | `{ tour_key, status, step_index }` |
| `POST {prefix}/{subject}/hints/dismiss` (`activation.hints.dismiss`) | `{ hint_id }` |
| `POST {prefix}/{subject}/celebrate` (`activation.celebrate`) | — |

Each returns `204`. Authorization defaults to `Gate::authorize('view', $subject)`; replace it with:

```php
Activation::authorizeUsing(function (Request $request, Model $subject): void {
    abort_unless($request->user()->belongsToTeam($subject), 403);
});
```

Or leave the routes off and call `ActivationProgress` from your own controllers.

## Contract with `@shapperize/react-tour` (v1)

- Payload: `tours: [{ tourKey, status, stepIndex }]`, `dismissedHints: string[]`.
- Statuses: `not_started | in_progress | completed | skipped`. Skipped and in-progress tours are resumable (`TourStatus::isResumable()`).
- Persist request body: `{ tour_key, status, step_index }`. Hint dismissal: `{ hint_id }`.
- Each step's `anchorId` refers to a `<TourAnchor id>` in the frontend.

## Testing

```sh
composer test
composer analyse
```

## License

MIT
