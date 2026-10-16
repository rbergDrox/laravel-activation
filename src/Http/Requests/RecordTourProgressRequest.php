<?php

namespace Shapperize\Activation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shapperize\Activation\Enums\TourStatus;

class RecordTourProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tour_key' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::enum(TourStatus::class)],
            'step_index' => ['nullable', 'integer', 'min:0', 'max:200'],
        ];
    }
}
