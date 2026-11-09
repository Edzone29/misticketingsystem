<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'name', 'is_working_day'];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_working_day' => 'boolean'];
    }
}
