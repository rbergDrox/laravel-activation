<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Steps
    |--------------------------------------------------------------------------
    |
    | Either a backed enum class whose cases implement ActivationStep, or a list
    | of ActivationStep classes resolved from the container. The order is the
    | order people are guided through them.
    |
    */

    'steps' => [],

    /*
    |--------------------------------------------------------------------------
    | Subject
    |--------------------------------------------------------------------------
    |
    | The model being activated: a workspace, team or account. It implements
    | Activatable and uses the HasActivation trait. `foreign_key` is the column
    | that points at the subject, both on the progress table and on every
    | watched model.
    |
    */

    'subject' => [
        'model' => null,
        'table' => 'teams',
        'foreign_key' => 'team_id',
        'key_type' => 'id', // 'id', 'ulid' or 'uuid', used by the published migration
        'completed_at_column' => 'activation_completed_at',
        'celebrated_at_column' => 'activation_celebrated_at',
    ],

    'user_model' => 'App\\Models\\User',

    'progress_table' => 'activation_tour_progress',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The computed step set is cached briefly, since it usually backs a nudge
    | shared on every response. Writes to watched models drop it early.
    |
    */

    'cache' => [
        'ttl' => 60,
        'prefix' => 'activation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Watched models
    |--------------------------------------------------------------------------
    |
    | Saving or deleting one of these drops the cached step set for the subject
    | the record belongs to, so a finished step shows on the next response
    | rather than whenever the TTL lapses.
    |
    */

    'watch' => [],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Optional endpoints for tour progress, hint dismissal and celebration.
    | Leave them off to call ActivationProgress from your own controllers.
    |
    */

    'routes' => [
        'enabled' => false,
        'prefix' => 'activation',
        'middleware' => ['web', 'auth'],
    ],

];
