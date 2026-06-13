<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\FormBuilder;
use App\Models\FormBuilderBlock;
use App\Models\FormBuilderSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormBuilderBlockController extends Controller
{
    // ─────────────────────────────────────────────
    // Add a block to a form builder
    // POST /agency/form-builders/{formBuilderId}/blocks
    // Body: { title, description }
    // ─────────────────────────────────────────────
    public function store(Request $request, $formBuilderId)
    {
        $form = $this->ownerForm($formBuilderId);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $serial = FormBuilderBlock::where('form_builder_id', $form->id)->max('serial') + 1;

            $block = FormBuilderBlock::create([
                'form_builder_id' => $form->id,
                'title' => $request->title,
                'description' => $request->description,
                'serial' => $serial,
            ]);

            // Auto-create default section so the block is usable immediately
            $section = FormBuilderSection::create([
                'form_builder_block_id' => $block->id,
                'name' => 'Untitled Section',
                'serial' => 0,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Block added',
                'data' => $block->load('sections'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error($e);
        }
    }

    // ─────────────────────────────────────────────
    // Update block title / description
    // PUT /agency/form-builder-blocks/{id}
    // ─────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $block = $this->ownerBlock($id);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $block->update($request->only(['title', 'description', 'status']));

        return response()->json(['status' => true, 'message' => 'Block updated', 'data' => $block->fresh()]);
    }

    // ─────────────────────────────────────────────
    // Delete a block (cascades to sections → fields)
    // DELETE /agency/form-builder-blocks/{id}
    // ─────────────────────────────────────────────
    public function destroy($id)
    {
        $block = $this->ownerBlock($id);
        $block->delete();

        return response()->json(['status' => true, 'message' => 'Block deleted']);
    }

    // ─────────────────────────────────────────────
    // Reorder blocks
    // POST /agency/form-builder-blocks/reorder
    // Body: { blocks: [{ id, serial }, ...] }
    // ─────────────────────────────────────────────
    public function reorder(Request $request)
    {
        $request->validate(['blocks' => 'required|array', 'blocks.*.id' => 'required|integer', 'blocks.*.serial' => 'required|integer']);

        DB::beginTransaction();

        try {
            $agencyId = auth('api')->user()->agency_id;

            foreach ($request->blocks as $item) {
                FormBuilderBlock::whereHas('formBuilder', fn ($q) => $q->where('agency_id', $agencyId))
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

    private function ownerForm($formBuilderId)
    {
        return FormBuilder::where('id', $formBuilderId)
            ->where('agency_id', auth('api')->user()->agency_id)
            ->firstOrFail();
    }

    private function ownerBlock($id)
    {
        return FormBuilderBlock::whereHas('formBuilder', function ($q) {
            $q->where('agency_id', auth('api')->user()->agency_id);
        })->findOrFail($id);
    }

    private function error(\Throwable $e)
    {
        return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
    }
}
