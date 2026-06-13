<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\FormBuilderField;
use App\Models\FormBuilderFieldItem;
use App\Models\FormBuilderSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormBuilderFieldController extends Controller
{
    // ─────────────────────────────────────────────
    // Add a field to a section
    // POST /agency/form-builder-sections/{sectionId}/fields
    //
    // Minimal body:
    //   { field_type, field_label }
    //
    // Optional body:
    //   profile_label, placeholder, is_mandatory, settings, items
    //
    // settings examples per type:
    //   rating / rating_group  : { "max_rating": 5 }
    //   dropdown               : { "select_multiple": false }
    //   radio                  : { "layout": "vertical", "yes_no": false }
    //   radio_table            : (items supply columns + rows)
    //   checkbox_table         : (items supply columns + rows)
    //   date_time_picker       : { "multiple_line": false }
    //   booking_section        : { "short_term": true, "long_term": true, "agency_fee": 100 }
    //   file_upload            : { "list_file": false, "additional_note": false }
    //   password               : { "confirm_password_label": "Confirm Password" }
    //   address_autocomplete   : (items supply address parts with toggle)
    //   stripe_subscription    : (items supply plan objects)
    //   salary_range           : {}
    //   payment                : { "billing_address": true, "additional_note": false }
    //
    // items structure:
    //   { item_type, label, value, meta, serial }
    //   item_type: "option" | "column" | "row" | "plan" | "address_part"
    // ─────────────────────────────────────────────
    public function store(Request $request, $sectionId)
    {
        $section = $this->ownerSection($sectionId);

        $allTypes = collect(FormBuilderField::FIELD_TYPES)->flatten()->toArray();

        $request->validate([
            'field_type' => 'required|in:'.implode(',', $allTypes),
            'field_label' => 'required|string|max:255',
            'profile_label' => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'is_mandatory' => 'nullable|boolean',
            'settings' => 'nullable|array',
            'items' => 'nullable|array',
            'items.*.item_type' => 'nullable|string',
            'items.*.label' => 'required_with:items|string',
            'items.*.value' => 'nullable|string',
            'items.*.meta' => 'nullable|array',
            'items.*.serial' => 'nullable|integer',
        ]);

        DB::beginTransaction();

        try {
            $serial = FormBuilderField::where('form_builder_section_id', $section->id)->max('serial') + 1;

            $field = FormBuilderField::create([
                'form_builder_section_id' => $section->id,
                'field_type' => $request->field_type,
                'field_label' => $request->field_label,
                'profile_label' => $request->profile_label,
                'placeholder' => $request->placeholder,
                'is_mandatory' => $request->boolean('is_mandatory'),
                'serial' => $serial,
                'settings' => $request->settings,
            ]);

            $this->syncItems($field, $request->items ?? []);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Field added',
                'data' => $field->load('items'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error($e);
        }
    }

    // ─────────────────────────────────────────────
    // Update a field
    // PUT /agency/form-builder-fields/{id}
    // ─────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $field = $this->ownerField($id);

        $allTypes = collect(FormBuilderField::FIELD_TYPES)->flatten()->toArray();

        $request->validate([
            'field_type' => 'sometimes|required|in:'.implode(',', $allTypes),
            'field_label' => 'sometimes|required|string|max:255',
            'profile_label' => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'is_mandatory' => 'nullable|boolean',
            'settings' => 'nullable|array',
            'items' => 'nullable|array',
        ]);

        DB::beginTransaction();

        try {
            $field->update($request->only([
                'field_type', 'field_label', 'profile_label',
                'placeholder', 'is_mandatory', 'settings',
            ]));

            if ($request->has('items')) {
                $this->syncItems($field, $request->items);
            }

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Field updated', 'data' => $field->fresh()->load('items')]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error($e);
        }
    }

    // ─────────────────────────────────────────────
    // Delete a field
    // DELETE /agency/form-builder-fields/{id}
    // ─────────────────────────────────────────────
    public function destroy($id)
    {
        $field = $this->ownerField($id);
        $field->delete();

        return response()->json(['status' => true, 'message' => 'Field deleted']);
    }

    // ─────────────────────────────────────────────
    // Reorder fields within a section
    // POST /agency/form-builder-fields/reorder
    // Body: { fields: [{ id, serial }, ...] }
    // ─────────────────────────────────────────────
    public function reorder(Request $request)
    {
        $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|integer',
            'fields.*.serial' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            $agencyId = auth('api')->user()->agency_id;

            foreach ($request->fields as $item) {
                FormBuilderField::whereHas('section.block.formBuilder', fn ($q) => $q->where('agency_id', $agencyId))
                    ->where('id', $item['id'])
                    ->update(['serial' => $item['serial']]);
            }

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Reordered']);

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error($e);
        }
    }

    // ───────────── helpers ─────────────

    private function syncItems(FormBuilderField $field, array $items)
    {
        // Full replace — delete existing then insert new
        $field->items()->delete();

        foreach ($items as $i => $item) {
            FormBuilderFieldItem::create([
                'form_builder_field_id' => $field->id,
                'item_type' => $item['item_type'] ?? 'option',
                'label' => $item['label'],
                'value' => $item['value'] ?? null,
                'meta' => $item['meta'] ?? null,
                'serial' => $item['serial'] ?? $i,
            ]);
        }
    }

    private function ownerSection($sectionId)
    {
        return FormBuilderSection::whereHas('block.formBuilder', function ($q) {
            $q->where('agency_id', auth('api')->user()->agency_id);
        })->findOrFail($sectionId);
    }

    private function ownerField($id)
    {
        return FormBuilderField::whereHas('section.block.formBuilder', function ($q) {
            $q->where('agency_id', auth('api')->user()->agency_id);
        })->findOrFail($id);
    }

    private function error(\Throwable $e)
    {
        return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
    }
}
