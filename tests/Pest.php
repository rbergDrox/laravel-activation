<?php

use Shapperize\Activation\Tests\Fixtures\User;
use Shapperize\Activation\Tests\Fixtures\Workspace;
use Shapperize\Activation\Tests\TestCase;

uses(TestCase::class)->in('Feature');

function workspace(): Workspace
{
    return Workspace::query()->create(['name' => 'Acme']);
}

function user(): User
{
    return User::query()->create(['name' => 'Ada']);
}

/** Completes the given steps by creating the widgets they look for. */
function complete(Workspace $workspace, string ...$kinds): Workspace
{
    foreach ($kinds as $kind) {
        $workspace->widgets()->create(['kind' => $kind]);
    }

    return $workspace;
}
