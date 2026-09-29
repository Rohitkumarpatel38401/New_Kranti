<?php

namespace App\Models;

use App\Models\Concerns\IsWebsiteContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasFactory, IsWebsiteContent;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['title' => 'array', 'is_active' => 'boolean'];
    }
}
