<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModCard extends Model
{
    protected $connection = 'mysql_s';
    protected $table = 'mod_cards';
    public $timestamps = false;
}
