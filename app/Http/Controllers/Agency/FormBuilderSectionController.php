<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\FormBuilderBlock;
use App\Models\FormBuilderSection;
use Illuminate\Http\Request;

class FormBuilderSectionController extends Controller
{
    // ─────────────────────────────────────────────
    // Add a section to a block
    // POST /agency/form-builder-blocks/{blockId}/sections
    // Body: { name }
    // ─────────────────────────────────────────────
    public function store(Request $request, $blockId)
    {
        $block = $this->ownerBlock($blockId);

        $request->validate(['name' => 'nullable|string|max:255']);

        $serial = FormBuilderSection::where('form_builder_block_id', $block->id)->max('serial') + 1;

        $section = FormBuilderSection::create([
            'form_builder_block_id' => $block->id,
            'name'                  => $request->name ?? 'Untitled Section',
            'serial'                => $serial,
        ]);

        return response()->json(['status' => true, 'message' => 'Section added', 'data' => $section], 201);
    }

    // ─────────────────────────────────────────────
    // Update section name
    // PUT /agency/form-builder-sections/{id}
    // Body: { name }
    // ─────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $section = $this->ownerSection($id);

        $request->validate(['name' => 'required|string|max:255']);

        $section->update(['name' => $request->name]);

        return response()->json(['status' => true, 'message' => 'Section updated', 'data' => $section->fresh()]);
    }

    // ─────────────────────────────────────────────
    // Delete a section (cascades to fields → items)
    // DELETE /agency/form-builder-sections/{id}
    // ─────────────────────────────────────────────
    public function destroy($id)
    {
        $section = $this->ownerSection($id);
        $section->delete();

        return response()->json(['status' => true, 'message' => 'Section deleted']);
    }

    // ───────────── helpers ─────────────

    private function ownerBlock($blockId)
    {
        return FormBuilderBlock::whereHas('formBuilder', function ($q) {
            $q->where('agency_id', auth('api')->user()->agency_id);
        })->findOrFail($blockId);
    }

    private function ownerSection($id)
    {
        return FormBuilderSection::whereHas('block.formBuilder', function ($q) {
            $q->where('agency_id', auth('api')->user()->agency_id);
        })->findOrFail($id);
    }
}
