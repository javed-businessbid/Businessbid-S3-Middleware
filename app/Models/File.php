<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;

class File extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'client_id',
        'owner_id',
        'file_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'type',
        'size',
        'category',
        'access_level',
        'version',
    ];

    protected $casts = [
        'workspace_id' => 'integer',
        'client_id' => 'integer',
        'owner_id' => 'integer',
        'size' => 'integer',
        'version' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
