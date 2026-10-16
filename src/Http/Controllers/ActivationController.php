<?php

namespace Shapperize\Activation\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shapperize\Activation\Activation;
use Shapperize\Activation\ActivationProgress;
use Shapperize\Activation\Config;
use Shapperize\Activation\Contracts\Activatable;
use Shapperize\Activation\Enums\TourStatus;
use Shapperize\Activation\Http\Requests\RecordTourProgressRequest;

class ActivationController
{
    public function __construct(private readonly ActivationProgress $progress) {}

    public function recordTour(RecordTourProgressRequest $request, string $subject): Response
    {
        $model = $this->subject($request, $subject);

        /** @var array{tour_key: string, status: string, step_index?: int|string|null} $data */
        $data = $request->validated();

        $this->progress->recordTour(
            $model,
            $this->user($request),
            $data['tour_key'],
            TourStatus::from($data['status']),
            (int) ($data['step_index'] ?? 0),
        );

        return response()->noContent();
    }

    public function dismissHint(Request $request, string $subject): Response
    {
        $model = $this->subject($request, $subject);

        $request->validate(['hint_id' => ['required', 'string', 'max:64']]);

        $this->progress->dismissHint($model, $this->user($request), (string) $request->string('hint_id'));

        return response()->noContent();
    }

    public function celebrate(Request $request, string $subject): Response
    {
        $this->progress->celebrate($this->subject($request, $subject));

        return response()->noContent();
    }

    private function subject(Request $request, string $key): Activatable&Model
    {
        $model = Config::subjectModel();

        /** @var Activatable&Model $subject */
        $subject = $model::query()->findOrFail($key);

        Activation::authorize($request, $subject);

        return $subject;
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();

        abort_unless($user instanceof Authenticatable, 401);

        return $user;
    }
}
