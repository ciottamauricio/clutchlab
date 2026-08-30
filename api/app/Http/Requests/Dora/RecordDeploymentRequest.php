<?php

namespace App\Http\Requests\Dora;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the shared secret is checked by the internal.token middleware
    }

    public function rules(): array
    {
        return [
            'service' => ['required', Rule::in(['api', 'worker', 'web'])],
            'environment' => ['sometimes', 'string', 'max:32'],
            // A full 40-char git sha; a short sha would break traceability back to the commit.
            'commit_sha' => ['required', 'string', 'size:40', 'regex:/^[0-9a-f]{40}$/'],
            'commit_authored_at' => ['required', 'date'],
            'deploy_started_at' => ['required', 'date'],
            'deploy_finished_at' => ['required', 'date', 'after_or_equal:deploy_started_at'],
            'status' => ['required', Rule::in(['success', 'failed'])],
            'actions_run_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    // Timestamps arrive from CI as ISO-8601, and a runner in a non-UTC zone sends a real
    // offset (…T10:00:00+02:00). Casting that straight to a `timestamp` column keeps the
    // wall clock and drops the offset, so the instant silently moves — and lead time, a
    // subtraction between two such instants, is wrong by the difference. Normalize here,
    // at the boundary, which is the only place that still knows the offset was there.
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();

        foreach (['commit_authored_at', 'deploy_started_at', 'deploy_finished_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Carbon::parse($data[$field])->utc();
            }
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }

    public function messages(): array
    {
        return [
            'service.in' => 'deployment.unknown_service',
            'commit_sha.regex' => 'deployment.invalid_sha',
            'commit_sha.size' => 'deployment.invalid_sha',
            'status.in' => 'deployment.invalid_status',
            'deploy_finished_at.after_or_equal' => 'deployment.finished_before_started',
        ];
    }
}
