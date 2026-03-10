<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyEvent extends Model
{
    protected $fillable = [
        'agency_id',
        'event_type_id',
        'event_title',
        'event_date',
        'event_time',
        'event_time_zone',
        'client_id',
        'candidate_id',
        'location',
        'interview_link',
        'note',
        'is_email_notify',
        'assign_user_id'
    ];

    public function eventType()
    {
        return $this->belongsTo(AgencyEventType::class, 'event_type_id');
    }

    // public function client() {
    //     return $this->belongsTo(Client::class);
    // }

    // public function candidate() {
    //     return $this->belongsTo(Candidate::class);
    // }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assign_user_id');
    }

    protected $casts = [
        'event_time' => 'datetime:h:i A',
    ];
}
