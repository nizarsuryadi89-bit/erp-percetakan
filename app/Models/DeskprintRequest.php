<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['branch_id', 'actor_id', 'request_key', 'fingerprint', 'result'])]
#[Hidden(['fingerprint', 'request_key'])]
class DeskprintRequest extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
