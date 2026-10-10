<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['branch_id', 'name', 'phone', 'address'])]
#[Hidden([])]
class Customer extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return [];
    }
}
