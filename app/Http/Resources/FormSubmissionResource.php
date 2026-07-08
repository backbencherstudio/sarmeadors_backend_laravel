<?php

namespace App\Http\Resources;

use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Shapes a client/candidate form submission into labeled answers so the
 * dynamic, per-agency fields stay understandable in the UI (both the
 * legacy per-field `values` rows and the builder's JSON `data` payload
 * are resolved against the form's field definitions for their label/type).
 *
 * @mixin FormSubmission
 */
class FormSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $schemaFields = collect($this->form?->schema['blocks'] ?? [])
            ->flatMap(fn ($block) => $block['sections'] ?? [])
            ->flatMap(fn ($section) => $section['fields'] ?? [])
            ->keyBy('name');

        $answers = $this->values
            ->filter(fn ($value) => $value->field !== null)
            ->map(fn ($value) => [
                'key' => $value->field->name ?? (string) $value->field->id,
                'label' => $value->field->label,
                'type' => $value->field->type,
                'value' => $value->value,
                'placeholder' => $value->field->placeholder,
                'is_required' => $value->field->is_required,
                'width' => $value->field->width,
            ])
            ->values();

        foreach ((array) $this->data as $key => $value) {
            $field = $schemaFields->get($key);

            $answers->push([
                'key' => $key,
                'label' => $field['label'] ?? Str::headline($key),
                'type' => $field['type'] ?? null,
                'value' => $value,
                'placeholder' => $field['placeholder'] ?? null,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'width' => $field['width'] ?? null,
            ]);
        }

        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'form_name' => $this->form?->name,
            'submitted_at' => $this->created_at,
            'answers' => $answers->values(),
        ];
    }
}
