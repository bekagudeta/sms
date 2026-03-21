<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_code',
        'building',
        'floor',
        'capacity',
        'type',
        'has_projector',
        'has_computers',
        'computer_count'
    ];

    protected $casts = [
        'has_projector' => 'boolean',
        'has_computers' => 'boolean',
        'capacity' => 'integer',
        'floor' => 'integer',
        'computer_count' => 'integer'
    ];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}