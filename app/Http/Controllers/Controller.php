<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

abstract class Controller
{
    protected function activeGroup(int $studentId): ?object
    {
        return DB::table('student_active_groups')
            ->where('student_id', $studentId)
            ->first();
    }
}
