<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Messages\LongTermJobMessageController;
use App\Models\Client;
use App\Models\LongTermJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class JobMessageController extends LongTermJobMessageController
{
    protected function resolveActor(Request $request): ?Model
    {
        return Client::where('email', $request->user()->email)
            ->where('agency_id', $request->current_agency->id)
            ->first();
    }

    protected function isAuthorized(Model $actor, LongTermJob $job): bool
    {
        return $job->client_id === $actor->id;
    }

    protected function thread(): string
    {
        return 'client';
    }
}
