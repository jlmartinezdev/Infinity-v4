<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketAsunto extends Model
{
    protected $table = 'ticket_asuntos';

    protected $fillable = ['nombre'];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_asunto_id', 'id');
    }
}
