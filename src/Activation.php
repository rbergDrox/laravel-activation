<?php

namespace Shapperize\Activation;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Shapperize\Activation\Contracts\Activatable;

/**
 * Hooks for the few decisions that do not fit in config.
 */
class Activation
{
    /** @var (Closure(Model): ?string)|null */
    private static ?Closure $subjectKeyResolver = null;

    /** @var (Closure(Request, Activatable&Model): void)|null */
    private static ?Closure $authorizer = null;

    /**
     * Tells the cache invalidation which subject a watched record belongs to,
     * for models that do not carry the configured foreign key.
     *
     * @param  (Closure(Model): ?string)|null  $callback
     */
    public static function resolveSubjectKeyUsing(?Closure $callback): void
    {
        self::$subjectKeyResolver = $callback;
    }

    /**
     * Replaces the default `Gate::authorize('view', $subject)` check on the
     * package routes. The callback should throw to refuse.
     *
     * @param  (Closure(Request, Activatable&Model): void)|null  $callback
     */
    public static function authorizeUsing(?Closure $callback): void
    {
        self::$authorizer = $callback;
    }

    public static function subjectKeyFor(Model $record): ?string
    {
        if (self::$subjectKeyResolver !== null) {
            return (self::$subjectKeyResolver)($record);
        }

        $key = $record->getAttribute(Config::foreignKey());

        return is_string($key) || is_int($key) ? (string) $key : null;
    }

    public static function authorize(Request $request, Activatable&Model $subject): void
    {
        if (self::$authorizer !== null) {
            (self::$authorizer)($request, $subject);

            return;
        }

        Gate::authorize('view', $subject);
    }

    /** Clears every hook. Meant for tests. */
    public static function flush(): void
    {
        self::$subjectKeyResolver = null;
        self::$authorizer = null;
    }
}
