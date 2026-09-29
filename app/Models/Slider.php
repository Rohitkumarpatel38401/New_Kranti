<?php

namespace App\Models;

use App\Models\Concerns\IsWebsiteContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory, IsWebsiteContent;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['label' => 'array', 'title' => 'array', 'subtitle' => 'array', 'button_text' => 'array', 'is_active' => 'boolean'];
    }
}
