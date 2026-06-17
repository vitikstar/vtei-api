<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: '/dashboard',
        summary: 'Головний дашборд студента',
        security: [['BearerAuth' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'Статистика студента'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        // Отримати групу студента
        $group = DB::connection('mysql')
            ->table('asu_grupa_student')
            ->where('student_id', $student->id)
            ->where('archive', 0)
            ->first();

        if (!$group) {
            return response()->json([
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'photo_url' => $student->photo_url,
                ],
                'group' => null,
                'lessons_today' => 0,
                'disciplines_count' => 0,
                'average_grade' => null,
                'upcoming_exams' => [],
            ]);
        }

        $today = now()->format('Y-m-d');
        $dayOfWeek = now()->dayOfWeekIso; // 1=Пн, 7=Нд

        // Заняття сьогодні для групи студента
        $lessonsToday = DB::connection('mysql')
            ->table('rozklad_nv_timetable_classes as r')
            ->join('rozklad_nv_timetable_classes_group_st as rg', 'rg.timetable_id', '=', 'r.id')
            ->where('rg.grupa_id', $group->grupa_id)
            ->where('r.day', $dayOfWeek)
            ->where('r.year', $this->currentYear())
            ->whereNull('r.date')
            ->orWhere(function ($q) use ($group, $today) {
                $q->where('rg.grupa_id', $group->grupa_id)
                    ->where('r.date', $today);
            })
            ->count();

        // Кількість дисциплін (модульні картки) для групи
        $disciplinesCount = DB::connection('mysql_s')
            ->table('mod_cards')
            ->where('st_group', $group->grupa_id)
            ->where('teach_year', $this->currentYear())
            ->count();

        // Середній бал по mod_list для студента
        $avgGrade = DB::connection('mysql_s')
            ->table('mod_list')
            ->where('stud_id', $student->id)
            ->whereNotIn('total', ['', '0', 'н/а'])
            ->whereNotNull('total')
            ->avg(DB::raw('CAST(total AS DECIMAL(5,2))'));

        // Найближчі екзамени
        $upcomingExams = DB::connection('mysql_s')
            ->table('dec_exam_header as h')
            ->join('subjects as s', 's.id', '=', 'h.subj_id')
            ->where('h.group_id', $group->grupa_id)
            ->where('h.year', $this->currentYear())
            ->where('h.date', '>=', $today)
            ->orderBy('h.date')
            ->limit(5)
            ->get(['h.date', 's.subject as subject', 'h.is_exam'])
            ->map(fn($e) => [
                'subject' => $e->subject,
                'date' => $e->date,
                'type' => $e->is_exam ? 'exam' : 'credit',
            ]);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'photo_url' => $student->photo_url,
            ],
            'group' => [
                'cb_number' => $group->cb_number,
                'grupa_id' => $group->grupa_id,
            ],
            'lessons_today' => $lessonsToday,
            'disciplines_count' => $disciplinesCount,
            'average_grade' => $avgGrade ? round($avgGrade, 1) : null,
            'current_year' => $this->currentYear(),
            'upcoming_exams' => $upcomingExams,
        ]);
    }

    private function currentYear(): string
    {
        $year = now()->year;
        $month = now()->month;
        // Навчальний рік починається у вересні
        if ($month >= 9) {
            return $year . '/' . ($year + 1);
        }
        return ($year - 1) . '/' . $year;
    }
}
