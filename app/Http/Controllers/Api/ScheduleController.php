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
        'lecture_hours'    => 'Лекція',
        'lecturing_hours'  => 'Лекція',
        'practical_hours'  => 'Практика',
        'lab_hours'        => 'Лабораторна',
        'seminar_hours'    => 'Семінар',
        'consultation'     => 'Консультація',
        'individual_hours' => 'Індивідуальна',
    ];

    #[OA\Get(
        path: '/schedule',
        summary: 'Розклад занять на тиждень',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        parameters: [
            new OA\Parameter(name: 'week_start', in: 'query', description: 'Початок тижня (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'group_id', in: 'query', description: 'ID групи (за замовчуванням — група студента)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Розклад на тиждень'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $groupId = $request->query('group_id')
            ?? $this->activeGroup($request)?->grupa_id;

        if (!$groupId) {
            return response()->json(['message' => 'Групу не знайдено'], 404);
        }

        $weekStart = $request->query('week_start')
            ? Carbon::parse($request->query('week_start'))->startOfDay()
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->addDays(6);

        $lessons = $this->getLessonsForPeriod($groupId, $weekStart, $weekEnd);
        $callSchedule = $this->getCallSchedule();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayNum = $i + 1;
            $dayLessons = $lessons->filter(fn($l) => $l->day == $dayNum)->values();

            $days[] = [
                'date'    => $date->format('Y-m-d'),
                'weekday' => $this->dayNames[$dayNum],
                'lessons' => $dayLessons->map(fn($l) => $this->formatLesson($l, $callSchedule))->values(),
            ];
        }

        return response()->json([
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end'   => $weekEnd->format('Y-m-d'),
            'group_id'   => (int) $groupId,
            'days'       => $days,
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
                'p.name as subject',
                'u.t_name as teacher_name', 'u.short_t_name as teacher_short',
                'u.email as teacher_email',
                'a.title as auditorium', 'a.case_number as building',
            )
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Заняття не знайдено'], 404);
        }

        $callSchedule = $this->getCallSchedule();
        $para = $callSchedule[$lesson->para] ?? null;

        // Групи для цього заняття
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
            'lesson_type' => $this->lessonTypes[$lesson->type_hours] ?? $lesson->type_hours,
            'theme'       => $lesson->theme_name,
            'homework'    => $lesson->homework,
            'notes'       => $lesson->notes,
            'is_remote'   => $lesson->is_remote === '1',
            'day'         => $this->dayNames[$lesson->day] ?? null,
            'date'        => $lesson->date,
            'para'        => $lesson->para,
            'time_start'  => $para?->start,
            'time_end'    => $para?->end,
            'teacher'     => [
                'name'  => $lesson->teacher_name,
                'short' => $lesson->teacher_short,
                'email' => $lesson->teacher_email,
            ],
            'auditorium'  => $lesson->auditorium,
            'building'    => $lesson->building,
            'groups'      => $groups,
        ]);
    }

    #[OA\Get(
        path: '/schedule/calendar',
        summary: 'Заняття у форматі для календаря',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        parameters: [
            new OA\Parameter(name: 'start', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'group_id', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Список подій')]
    )]
    public function calendar(Request $request): JsonResponse
    {
        $student = $request->user();

        $groupId = $request->query('group_id')
            ?? $this->activeGroup($request)?->grupa_id;

        if (!$groupId) {
            return response()->json([]);
        }

        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end   = Carbon::parse($request->query('end', now()->endOfMonth()));

        $lessons = $this->getLessonsForPeriod($groupId, $start, $end);
        $callSchedule = $this->getCallSchedule();

        $typeColors = [
            'lecture_hours'   => '#3788d8',
            'practical_hours' => '#28a745',
            'lab_hours'       => '#fd7e14',
            'seminar_hours'   => '#6f42c1',
        ];

        $events = $lessons->map(function ($l) use ($callSchedule, $typeColors, $start) {
            $para = $callSchedule[$l->para] ?? null;
            // Визначаємо дату події
            if ($l->date) {
                $date = $l->date;
            } else {
                // Знаходимо перший день тижня що відповідає $l->day
                $date = $start->copy()->startOfWeek()->addDays($l->day - 1)->format('Y-m-d');
            }

            return [
                'id'    => $l->id,
                'title' => $l->subject . ' (' . ($this->lessonTypes[$l->type_hours] ?? $l->type_hours) . ')',
                'start' => $date . 'T' . ($para?->start ?? '00:00') . ':00',
                'end'   => $date . 'T' . ($para?->end ?? '00:00') . ':00',
                'color' => $typeColors[$l->type_hours] ?? '#6c757d',
            ];
        });

        return response()->json($events->values());
    }

    private function getLessonsForPeriod(int $groupId, Carbon $from, Carbon $to)
    {
        return DB::connection('mysql')
            ->table('rozklad_nv_timetable_classes as r')
            ->join('rozklad_nv_timetable_classes_group_st as rg', 'rg.timetable_id', '=', 'r.id')
            ->leftJoin('asu_predmet as p', 'p.id', '=', 'r.predmet_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.teacher_id')
            ->leftJoin('rozklad_nv_auditory_data as a', 'a.id', '=', 'r.aud_id')
            ->where('rg.grupa_id', $groupId)
            ->whereBetween('r.date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('r.id')
            ->orderBy('r.day')
            ->orderBy('r.para')
            ->select(
                'r.id', 'r.day', 'r.para', 'r.date',
                'r.type_hours', 'r.theme_name', 'r.is_remote',
                'p.name as subject',
                'u.t_name as teacher_name', 'u.short_t_name as teacher_short',
                'a.title as auditorium', 'a.case_number as building',
            )
            ->get();
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
            'id'          => $lesson->id,
            'para'        => (int) $lesson->para,
            'time_start'  => $para?->start,
            'time_end'    => $para?->end,
            'subject'     => $lesson->subject,
            'teacher'     => $lesson->teacher_short ?? $lesson->teacher_name,
            'auditorium'  => $lesson->auditorium,
            'building'    => $lesson->building,
            'lesson_type' => $this->lessonTypes[$lesson->type_hours] ?? $lesson->type_hours,
            'theme'       => $lesson->theme_name,
            'is_remote'   => $lesson->is_remote === '1',
        ];
    }
}
