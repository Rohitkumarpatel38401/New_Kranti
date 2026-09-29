<?php

namespace App\Models;

use App\Models\Concerns\IsWebsiteContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory, IsWebsiteContent;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quote' => 'array', 'is_active' => 'boolean'];
    }
}
