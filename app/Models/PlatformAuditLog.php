<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'target_user_id', 'action', 'metadata'])]
class PlatformAuditLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
