<?php

namespace Shapperize\Activation\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Shapperize\Activation\Activation;
use Shapperize\Activation\ActivationServiceProvider;
use Shapperize\Activation\Tests\Fixtures\FixtureStep;
use Shapperize\Activation\Tests\Fixtures\User;
use Shapperize\Activation\Tests\Fixtures\Widget;
use Shapperize\Activation\Tests\Fixtures\Workspace;

abstract class TestCase extends Orchestra
{
    protected function tearDown(): void
    {
        Activation::flush();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [ActivationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('activation.steps', FixtureStep::class);
        $app['config']->set('activation.subject', [
            'model' => Workspace::class,
            'table' => 'workspaces',
            'foreign_key' => 'workspace_id',
            'key_type' => 'id',
            'completed_at_column' => 'activation_completed_at',
            'celebrated_at_column' => 'activation_celebrated_at',
        ]);
        $app['config']->set('activation.user_model', User::class);
        $app['config']->set('activation.watch', [Widget::class]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        if (env('DB_CONNECTION') === 'pgsql') {
            $app['config']->set('database.default', 'pgsql');
        } else {
            $app['config']->set('database.default', 'testing');
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('activation_tour_progress');
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->timestamps();
        });

        (include __DIR__.'/../database/migrations/add_activation_columns_to_subject_table.php.stub')->up();
        (include __DIR__.'/../database/migrations/create_activation_tour_progress_table.php.stub')->up();
    }
}
