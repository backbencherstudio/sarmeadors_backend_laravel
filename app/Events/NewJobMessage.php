<?php

namespace App\Events;

use App\Models\JobMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewJobMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public JobMessage $message) {}

    public function broadcastOn(): Channel
    {
        if ($this->message->short_term_job_id) {
            return new PrivateChannel('short-term-job-messages.'.$this->message->short_term_job_id);
        }

        return new PrivateChannel('job-messages.'.$this->message->long_term_job_id);
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'long_term_job_id' => $this->message->long_term_job_id,
            'short_term_job_id' => $this->message->short_term_job_id,
            'thread' => $this->message->thread,
            'message' => $this->message->message,
            'file_url' => $this->message->file_url,
            'file_name' => $this->message->file_name,
            'read_at' => $this->message->read_at,
            'created_at' => $this->message->created_at,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ],
        ];
    }
}
