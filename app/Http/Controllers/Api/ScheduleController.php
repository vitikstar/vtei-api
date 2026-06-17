<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ScheduleController extends Controller
{
    private array $dayNames = [
        1 => 'Понеділок',
        2 => 'Вівторок',
        3 => 'Середа',
        4 => 'Четвер',
        5 => 'П\'ятниця',
        6 => 'Субота',
        7 => 'Неділя',
    ];

    private array $lessonTypes = [
        'lecturing_hours'           => 'Лекція',
        'lecture_hours'             => 'Лекція',
        'practical_hours'           => 'Практика',
        'lab_hours'                 => 'Лабораторна',
        'seminar_hours'             => 'Семінар',
        'consult_hours'             => 'Консультація',
        'consult_mag_hours'         => 'Консультація (маг.)',
        'individual_hours'          => 'Індивідуальна',
        'labor_hours'               => 'Самостійна',
        'zalik_hours'               => 'Залік',
        'semester_exams_hours'      => 'Екзамен',
        'certification_exams_hours' => 'Атестація',
        'coursework_protection_hours' => 'Захист курсової',
        'conducting_protection_hours' => 'Захист',
        'briefing_hours'            => 'Інструктаж',
    ];

    #[OA\Get(
        path: '/schedule',
        summary: 'Розклад занять',
        description: 'За замовчуванням повертає весь поточний навчальний рік. Керується параметрами period або date_from/date_to.',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        parameters: [
            new OA\Parameter(name: 'period', in: 'query', description: 'Швидкий вибір: year (за замовч.), month, week, day', schema: new OA\Schema(type: 'string', enum: ['year', 'month', 'week', 'day'])),
            new OA\Parameter(name: 'date', in: 'query', description: 'Опорна дата для period (Y-m-d). За замовч. — сьогодні', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_from', in: 'query', description: 'Початок довільного діапазону (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', description: 'Кінець довільного діапазону (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Фільтр по викладачу (ID з таблиці users)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'auditorium_id', in: 'query', description: 'Фільтр по аудиторії', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'subject_id', in: 'query', description: 'Фільтр по дисципліні', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'lesson_type', in: 'query', description: 'Фільтр по типу заняття (lecturing_hours, practical_hours, ...)', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Розклад згрупований по днях'),
            new OA\Response(response: 401, description: 'Не авторизований'),
            new OA\Response(response: 404, description: 'Групу не знайдено'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $groupId = $this->activeGroup($request)?->grupa_id;
        if (!$groupId) {
            return response()->json(['message' => 'Групу не знайдено'], 404);
        }

        [$from, $to] = $this->resolvePeriod($request);

        $lessons = $this->queryLessons($groupId, $from, $to, $request);
        $callSchedule = $this->getCallSchedule();

        // Групуємо по даті
        $grouped = $lessons->groupBy('date')->map(function ($dayLessons, $date) use ($callSchedule) {
            $carbon = Carbon::parse($date);
            return [
                'date'    => $date,
                'weekday' => $this->dayNames[$carbon->dayOfWeekIso] ?? '',
                'lessons' => $dayLessons->map(fn($l) => $this->formatLesson($l, $callSchedule))->values(),
            ];
        })->sortKeys()->values();

        return response()->json([
            'period'    => $request->query('period', 'year'),
            'date_from' => $from->format('Y-m-d'),
            'date_to'   => $to->format('Y-m-d'),
            'grupa_id'  => (int) $groupId,
            'days'      => $grouped,
        ]);
    }

    #[OA\Get(
        path: '/schedule/lesson/{id}',
        summary: 'Детальна інформація про заняття',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Деталі заняття'),
            new OA\Response(response: 404, description: 'Заняття не знайдено'),
        ]
    )]
    public function lesson(Request $request, int $id): JsonResponse
    {
        $lesson = DB::connection('mysql')
            ->table('rozklad_nv_timetable_classes as r')
            ->leftJoin('asu_predmet as p', 'p.id', '=', 'r.predmet_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.teacher_id')
            ->leftJoin('rozklad_nv_auditory_data as a', 'a.id', '=', 'r.aud_id')
            ->where('r.id', $id)
            ->select(
                'r.id', 'r.day', 'r.para', 'r.date', 'r.year',
                'r.type_hours', 'r.theme_name', 'r.homework',
                'r.notes', 'r.is_remote',
                'p.name as subject', 'p.id as subject_id',
                'u.id as teacher_id', 'u.t_name as teacher_name',
                'u.short_t_name as teacher_short', 'u.email as teacher_email',
                'a.id as auditorium_id', 'a.title as auditorium',
                'a.case_number as building',
            )
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Заняття не знайдено'], 404);
        }

        $callSchedule = $this->getCallSchedule();
        $para = $callSchedule[$lesson->para] ?? null;

        $groups = DB::connection('mysql')
            ->table('rozklad_nv_timetable_classes_group_st as rg')
            ->join('asu_grupa as g', 'g.id', '=', 'rg.grupa_id')
            ->where('rg.timetable_id', $id)
            ->groupBy('rg.grupa_id', 'g.name')
            ->pluck('g.name')
            ->toArray();

        return response()->json([
            'id'          => $lesson->id,
            'subject'     => $lesson->subject,
            'subject_id'  => $lesson->subject_id,
            'lesson_type' => $this->lessonTypes[$lesson->type_hours] ?? $lesson->type_hours,
            'lesson_type_key' => $lesson->type_hours,
            'theme'       => $lesson->theme_name,
            'homework'    => $lesson->homework,
            'notes'       => $lesson->notes,
            'is_remote'   => $lesson->is_remote === '1',
            'day'         => $this->dayNames[$lesson->day] ?? null,
            'date'        => $lesson->date,
            'para'        => (int) $lesson->para,
            'time_start'  => $para?->start,
            'time_end'    => $para?->end,
            'teacher'     => [
                'id'    => $lesson->teacher_id,
                'name'  => $lesson->teacher_name,
                'short' => $lesson->teacher_short,
                'email' => $lesson->teacher_email,
            ],
            'auditorium'     => $lesson->auditorium,
            'auditorium_id'  => $lesson->auditorium_id,
            'building'       => $lesson->building,
            'groups'         => $groups,
        ]);
    }

    // --- Приватні методи ---

    private function resolvePeriod(Request $request): array
    {
        // Якщо передано довільний діапазон — використовуємо його
        if ($request->query('date_from') && $request->query('date_to')) {
            return [
                Carbon::parse($request->query('date_from'))->startOfDay(),
                Carbon::parse($request->query('date_to'))->endOfDay(),
            ];
        }

        $anchor = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::now();

        return match ($request->query('period', 'year')) {
            'day'   => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'week'  => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => $this->currentAcademicYear(),
        };
    }

    private function currentAcademicYear(): array
    {
        $now = Carbon::now();
        if ($now->month >= 9) {
            return [
                Carbon::create($now->year, 9, 1)->startOfDay(),
                Carbon::create($now->year + 1, 8, 31)->endOfDay(),
            ];
        }
        return [
            Carbon::create($now->year - 1, 9, 1)->startOfDay(),
            Carbon::create($now->year, 8, 31)->endOfDay(),
        ];
    }

    private function queryLessons(int $groupId, Carbon $from, Carbon $to, Request $request)
    {
        $query = DB::connection('mysql')
            ->table('rozklad_nv_timetable_classes as r')
            ->join('rozklad_nv_timetable_classes_group_st as rg', 'rg.timetable_id', '=', 'r.id')
            ->leftJoin('asu_predmet as p', 'p.id', '=', 'r.predmet_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.teacher_id')
            ->leftJoin('rozklad_nv_auditory_data as a', 'a.id', '=', 'r.aud_id')
            ->where('rg.grupa_id', $groupId)
            ->whereBetween('r.date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('r.id')
            ->orderBy('r.date')
            ->orderBy('r.para')
            ->select(
                'r.id', 'r.day', 'r.para', 'r.date',
                'r.type_hours', 'r.theme_name', 'r.is_remote',
                'r.teacher_id', 'r.predmet_id', 'r.aud_id',
                'p.name as subject',
                'u.t_name as teacher_name', 'u.short_t_name as teacher_short',
                'a.title as auditorium', 'a.case_number as building',
            );

        if ($request->query('teacher_id')) {
            $query->where('r.teacher_id', $request->query('teacher_id'));
        }
        if ($request->query('auditorium_id')) {
            $query->where('r.aud_id', $request->query('auditorium_id'));
        }
        if ($request->query('subject_id')) {
            $query->where('r.predmet_id', $request->query('subject_id'));
        }
        if ($request->query('lesson_type')) {
            $query->where('r.type_hours', $request->query('lesson_type'));
        }

        return $query->get();
    }

    private function getCallSchedule(): \Illuminate\Support\Collection
    {
        return DB::connection('mysql')
            ->table('rozklad_nv_call_schedule')
            ->get()
            ->keyBy('number');
    }

    private function formatLesson(object $lesson, \Illuminate\Support\Collection $callSchedule): array
    {
        $para = $callSchedule[$lesson->para] ?? null;

        return [
            'id'              => $lesson->id,
            'para'            => (int) $lesson->para,
            'time_start'      => $para?->start,
            'time_end'        => $para?->end,
            'subject'         => $lesson->subject,
            'subject_id'      => $lesson->predmet_id,
            'teacher'         => $lesson->teacher_short ?? $lesson->teacher_name,
            'teacher_id'      => $lesson->teacher_id,
            'auditorium'      => $lesson->auditorium,
            'auditorium_id'   => $lesson->aud_id,
            'building'        => $lesson->building,
            'lesson_type'     => $this->lessonTypes[$lesson->type_hours] ?? $lesson->type_hours,
            'lesson_type_key' => $lesson->type_hours,
            'theme'           => $lesson->theme_name,
            'is_remote'       => $lesson->is_remote === '1',
        ];
    }
}
