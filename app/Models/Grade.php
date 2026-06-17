<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $connection = 'mysql_s';
    protected $table = 'mod_list';
    public $timestamps = false;

    public function card()
    {
        return $this->belongsTo(ModCard::class, 'mod_card_id');
    }
}
