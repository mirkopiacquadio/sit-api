<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComunitaMontanaSquadra extends Model
{
    use HasFactory;
    protected $table = 'squadre_cantieri';
    protected $guarded = [];
    protected $connection = 'cmtiternoaltotammaro';
}
