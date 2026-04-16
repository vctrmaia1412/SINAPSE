<?php

namespace App\Http\Requests\Api\V1\Participant;

use Illuminate\Foundation\Http\FormRequest;

class RegisterForEventRequest extends FormRequest
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
        return [];
    }
}
