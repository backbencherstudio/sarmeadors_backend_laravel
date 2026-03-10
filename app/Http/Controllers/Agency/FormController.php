<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'entity' => 'required'
        ]);

        $form = Form::create([
            'agency_id' => auth('api')->user()->agency_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'entity' => $request->entity
        ]);

        return response()->json([
            'status' => true,
            'data' => $form
        ]);
    }

    // {
    // "name":"Client Registration",
    // "entity":"client"
    // }

    public function show($slug)
    {
        $form = Form::with(['fields' => function($q){
            $q->orderBy('serial');
        }])->where('slug',$slug)->firstOrFail();

        return response()->json($form);
    }

}
