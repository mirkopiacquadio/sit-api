<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComunitaMontana extends Model
{
    use HasFactory;
    protected $table = 'cantieri';
    protected $guarded = [];
    protected $connection = 'cmtiternoaltotammaro';
    public $timestamps = false;

    public function squadra()
    {
        return $this->belongsTo(ComunitaMontanaSquadra::class, 'cod_squad', 'cod_squadra');
    }
}
