<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroup extends Model
{
    protected $connection = 'mysql_s';
    protected $table = 'asu_grupa_student';
    public $timestamps = false;

    protected $fillable = ['student_id', 'cb_number', 'grupa_id'];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
