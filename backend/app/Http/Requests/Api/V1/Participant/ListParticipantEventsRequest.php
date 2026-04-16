<?php

namespace App\Http\Requests\Api\V1\Participant;

use Illuminate\Foundation\Http\FormRequest;

class ListParticipantEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isParticipant() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'q' => ['sometimes', 'string', 'max:255'],
            'organizer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'starts_from' => ['sometimes', 'date'],
            'starts_until' => ['sometimes', 'date'],
        ];
    }
}
