<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $connection = 'mysql';
    protected $table = 'rozklad_nv_timetable_classes';
    public $timestamps = false;

    public function groups()
    {
        return $this->hasMany(LessonGroup::class, 'timetable_id');
    }
}
