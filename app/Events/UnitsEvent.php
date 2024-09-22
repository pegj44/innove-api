<?php

namespace App\Events;

use App\Http\Controllers\MachinesController;
use App\Http\Controllers\PusherController;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitsEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $action;
    public $arguments;
    public $userId;
    public $ip;
    public $machine;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, array $arguments, string $action, string $machine, string $ip)
    {
        $this->action = $action;
        $this->arguments = $arguments;
        $this->userId = $userId;
        $this->machine = $machine;
        $this->ip = $ip;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('unit.'. $this->userId .'.'. $this->ip),
        ];
    }
}
