<?php

namespace Shapperize\Activation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config as Repository;
use InvalidArgumentException;
use Shapperize\Activation\Contracts\Activatable;

/**
 * Typed access to `config/activation.php`.
 *
 * @internal
 */
final class Config
{
    /** @return class-string<Activatable&Model> */
    public static function subjectModel(): string
    {
        $model = Repository::get('activation.subject.model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class) || ! is_subclass_of($model, Activatable::class)) {
            throw new InvalidArgumentException('activation.subject.model must be an Eloquent model implementing '.Activatable::class.'.');
        }

        /** @var class-string<Activatable&Model> $model */
        return $model;
    }

    public static function foreignKey(): string
    {
        return self::string('activation.subject.foreign_key', 'team_id');
    }

    public static function completedAtColumn(): string
    {
        return self::string('activation.subject.completed_at_column', 'activation_completed_at');
    }

    public static function celebratedAtColumn(): string
    {
        return self::string('activation.subject.celebrated_at_column', 'activation_celebrated_at');
    }

    /** @return class-string<Model> */
    public static function userModel(): string
    {
        /** @var class-string<Model> */
        return self::string('activation.user_model', 'App\\Models\\User');
    }

    public static function progressTable(): string
    {
        return self::string('activation.progress_table', 'activation_tour_progress');
    }

    public static function cacheTtl(): int
    {
        $ttl = Repository::get('activation.cache.ttl', 60);

        return is_numeric($ttl) ? (int) $ttl : 60;
    }

    public static function cachePrefix(): string
    {
        return self::string('activation.cache.prefix', 'activation');
    }

    /** @return array<int, class-string<Model>> */
    public static function watched(): array
    {
        $watched = Repository::get('activation.watch', []);

        /** @var array<int, class-string<Model>> */
        return is_array($watched) ? array_values($watched) : [];
    }

    public static function routesEnabled(): bool
    {
        return (bool) Repository::get('activation.routes.enabled', false);
    }

    public static function routePrefix(): string
    {
        return self::string('activation.routes.prefix', 'activation');
    }

    /** @return array<int, string> */
    public static function routeMiddleware(): array
    {
        $middleware = Repository::get('activation.routes.middleware', ['web', 'auth']);

        /** @var array<int, string> */
        return is_array($middleware) ? array_values($middleware) : [];
    }

    private static function string(string $key, string $default): string
    {
        $value = Repository::get($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
