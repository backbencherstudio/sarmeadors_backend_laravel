<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\FormBuilder;
use App\Models\FormBuilderBlock;
use App\Models\FormBuilderSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormBuilderController extends Controller
{
    public function index()
    {
        $forms = FormBuilder::where('agency_id', $this->agencyId())
            ->orderByDesc('id')
            ->get();

        return response()->json(['status' => true, 'data' => $forms]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'form_type'        => 'required|in:section,application',
            'application_type' => 'required_if:form_type,application|nullable|in:registration,job_posting',
            'user_type'        => 'nullable|in:client,candidate',
            'job_type'         => 'nullable|in:shift_job,long_term_job',
            'select_type'      => 'nullable|in:add_user,long_term_job,schedule_interview_form,review_user',
        ]);

        DB::beginTransaction();

        try {
            $slug = $this->uniqueSlug($request->form_type, $request->application_type, $request->user_type);

            $form = FormBuilder::create([
                'agency_id'        => $this->agencyId(),
                'slug'             => $slug,
                'form_type'        => $request->form_type,
                'application_type' => $request->application_type,
                'user_type'        => $request->user_type,
                'job_type'         => $request->job_type,
                'select_type'      => $request->select_type,
            ]);

            $introBlock = FormBuilderBlock::create([
                'form_builder_id' => $form->id,
                'title'           => 'Introduction',
                'description'     => 'Set your logo & form title here',
                'serial'          => 0,
            ]);

            FormBuilderSection::create([
                'form_builder_block_id' => $introBlock->id,
                'name'                  => 'Intro Section',
                'serial'                => 0,
            ]);

            $this->seedPredefinedBlocks($form);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Form builder created',
                'data'    => $this->formWithStructure($form->id),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error($e);
        }
    }

    public function show($id)
    {
        $this->ownerForm($id);

        return response()->json([
            'status' => true,
            'data'   => $this->formWithStructure($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $form = $this->ownerForm($id);

        $request->validate([
            'title'        => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'button_label' => 'nullable|string|max:255',
            'button_link'  => 'nullable|string|max:500',
            'status'       => 'nullable|boolean',
        ]);

        $data = $request->only(['title', 'description', 'button_label', 'button_link', 'status']);

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|max:20480']);
            if ($form->getRawOriginal('logo')) {
                Storage::disk('public')->delete($form->getRawOriginal('logo'));
            }
            $data['logo'] = $request->file('logo')->store('form-builder/logos', 'public');
        }

        $form->update($data);

        return response()->json(['status' => true, 'message' => 'Updated', 'data' => $form->fresh()]);
    }

    public function publish($id)
    {
        $form = $this->ownerForm($id);
        $form->update(['is_published' => !$form->is_published]);

        $msg = $form->is_published ? 'Form published' : 'Form unpublished';

        return response()->json(['status' => true, 'message' => $msg, 'is_published' => $form->is_published]);
    }

    public function destroy($id)
    {
        $form = $this->ownerForm($id);

        if ($form->getRawOriginal('logo')) {
            Storage::disk('public')->delete($form->getRawOriginal('logo'));
        }

        $form->delete();

        return response()->json(['status' => true, 'message' => 'Deleted']);
    }

    public function publicShow($slug)
    {
        $form = FormBuilder::where('slug', $slug)
            ->where('is_published', true)
            ->where('status', true)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'data'   => $this->formWithStructure($form->id),
        ]);
    }


    private function agencyId()
    {
        return auth('api')->user()->agency_id;
    }

    private function ownerForm($id)
    {
        return FormBuilder::where('id', $id)
            ->where('agency_id', $this->agencyId())
            ->firstOrFail();
    }

    private function formWithStructure($id)
    {
        return FormBuilder::with([
            'blocks.sections.fields.items',
        ])->findOrFail($id);
    }

    private function uniqueSlug($formType, $appType, $userType)
    {
        $base = implode('-', array_filter([$formType, $appType, $userType]));
        $slug = Str::slug($base);
        $original = $slug;
        $i = 1;

        while (FormBuilder::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }

    private function error(\Throwable $e)
    {
        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong',
            'error'   => $e->getMessage(),
        ], 500);
    }

    private function seedPredefinedBlocks(FormBuilder $form)
    {
        $blocks = [];

        if ($form->form_type === 'application' && $form->application_type === 'registration') {

            if ($form->user_type === 'client') {
                $blocks = [
                    ['title' => 'Contact & Address',       'description' => 'Add contact and address information'],
                    ['title' => 'Children Information',    'description' => 'Children details'],
                    ['title' => 'Requirements',            'description' => 'Household requirements'],
                    ['title' => 'Additional Information',  'description' => 'Extra details'],
                ];
            } elseif ($form->user_type === 'candidate') {
                $blocks = [
                    ['title' => 'Contact & Address',      'description' => 'Add contact and address information'],
                    ['title' => 'Work Experience',        'description' => 'Past work history'],
                    ['title' => 'Skills & Qualifications','description' => 'Relevant skills'],
                    ['title' => 'Additional Information', 'description' => 'Extra details'],
                ];
            }

        } elseif ($form->form_type === 'application' && $form->application_type === 'job_posting') {

            $blocks = [
                ['title' => 'Job Details',         'description' => 'Provide the basic information about the job posting'],
                ['title' => 'Booking Date & Time', 'description' => 'Schedule when this job will take place'],
                ['title' => 'Job Address',         'description' => 'Provide detailed address about the job posting'],
                ['title' => 'Set the Budget',      'description' => 'Define the financial details for this job'],
            ];
        }

        foreach ($blocks as $i => $block) {
            $b = FormBuilderBlock::create([
                'form_builder_id' => $form->id,
                'title'           => $block['title'],
                'description'     => $block['description'],
                'serial'          => $i + 1,
            ]);

            FormBuilderSection::create([
                'form_builder_block_id' => $b->id,
                'name'                  => 'Untitled Section',
                'serial'                => 0,
            ]);
        }
    }
}
