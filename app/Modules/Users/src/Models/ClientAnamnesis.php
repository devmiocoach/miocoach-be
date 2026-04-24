<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAnamnesis extends Model
{
    protected $table = 'client_anamnesis';

    protected $fillable = ['client_id', 'content_encrypted', 'iv', 'tag'];

    protected $hidden = ['content_encrypted', 'iv', 'tag'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
