<?php

namespace App\Http\Requests\Api\V1\Organizer;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Event $event */
        $event = $this->route('event');

        return $this->user()->can('update', $event);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', 'required', Rule::enum(EventStatus::class)],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Event $event */
            $event = $this->route('event');

            $startsAt = $this->has('starts_at')
                ? Carbon::parse($this->input('starts_at'))
                : $event->starts_at;
            $endsAt = $this->has('ends_at')
                ? Carbon::parse($this->input('ends_at'))
                : $event->ends_at;

            if ($startsAt === null || $endsAt === null) {
                return;
            }

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $validator->errors()->add(
                    'ends_at',
                    'The end time must be after the start time.'
                );
            }

            if (
                $event->status === EventStatus::Cancelled
                && $this->filled('status')
                && (string) $this->input('status') !== EventStatus::Cancelled->value
            ) {
                $validator->errors()->add(
                    'status',
                    'A cancelled event cannot be reactivated.'
                );
            }
        });
    }
}
