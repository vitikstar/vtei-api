<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReferenceController extends Controller
{
    #[OA\Get(
        path: '/schedule/teachers',
        summary: 'Список викладачів згрупованих по кафедрах',
        description: 'Повертає викладачів з розкладу поточної групи, згрупованих по кафедрах.',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        responses: [
            new OA\Response(response: 200, description: 'Масив кафедр з викладачами'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function teachers(Request $request): JsonResponse
    {
        $groupId = $this->activeGroupId($request);

        $rows = DB::connection('mysql')
            ->table('users as u')
            ->join('rozklad_nv_timetable_classes as r', 'r.teacher_id', '=', 'u.id')
            ->join('rozklad_nv_timetable_classes_group_st as rg', 'rg.timetable_id', '=', 'r.id')
            ->leftJoin('asu_kafedra as k', 'k.id', '=', 'u.kafedra_id')
            ->when($groupId, fn($q) => $q->where('rg.grupa_id', $groupId))
            ->select(
                'u.id', 'u.t_name as name', 'u.short_t_name as short_name', 'u.email',
                'k.id as kafedra_id', 'k.name as kafedra_name',
            )
            ->distinct()
            ->orderBy('k.name')
            ->orderBy('u.t_name')
            ->get();

        $grouped = $rows->groupBy('kafedra_id')->map(function ($teachers, $kafedraId) {
            $first = $teachers->first();
            return [
                'kafedra_id'   => $kafedraId ? (int) $kafedraId : null,
                'kafedra_name' => $first->kafedra_name ?? 'Без кафедри',
                'teachers'     => $teachers->map(fn($t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'short_name' => $t->short_name,
                    'email'      => $t->email,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    #[OA\Get(
        path: '/schedule/auditoriums',
        summary: 'Список аудиторій згрупованих по корпусах',
        description: 'Повертає аудиторії з розкладу поточної групи, згруповані по корпусах.',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        responses: [
            new OA\Response(response: 200, description: 'Масив корпусів з аудиторіями'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function auditoriums(Request $request): JsonResponse
    {
        $groupId = $this->activeGroupId($request);

        $rows = DB::connection('mysql')
            ->table('rozklad_nv_auditory_data as a')
            ->join('rozklad_nv_timetable_classes as r', 'r.aud_id', '=', 'a.id')
            ->join('rozklad_nv_timetable_classes_group_st as rg', 'rg.timetable_id', '=', 'r.id')
            ->leftJoin('rozklad_nv_case_number as c', 'c.id', '=', 'a.case_number')
            ->when($groupId, fn($q) => $q->where('rg.grupa_id', $groupId))
            ->select(
                'a.id', 'a.title', 'a.floor',
                'c.id as building_id', 'c.name as building_name',
            )
            ->distinct()
            ->orderBy('c.name')
            ->orderBy('a.title')
            ->get();

        $grouped = $rows->groupBy('building_id')->map(function ($auditoriums, $buildingId) {
            $first = $auditoriums->first();
            return [
                'building_id'   => $buildingId ? (int) $buildingId : null,
                'building_name' => $first->building_name ?? 'Без корпусу',
                'auditoriums'   => $auditoriums->map(fn($a) => [
                    'id'    => $a->id,
                    'title' => $a->title,
                    'floor' => $a->floor,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    #[OA\Get(
        path: '/schedule/subjects',
        summary: 'Список дисциплін',
        description: 'Повертає дисципліни з розкладу поточної групи.',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        responses: [
            new OA\Response(response: 200, description: 'Список дисциплін'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function subjects(Request $request): JsonResponse
    {
        $groupId = $this->activeGroupId($request);

        $data = DB::connection('mysql')
            ->table('asu_predmet as p')
            ->join('rozklad_nv_timetable_classes as r', 'r.predmet_id', '=', 'p.id')
            ->join('rozklad_nv_timetable_classes_group_st as rg', 'rg.timetable_id', '=', 'r.id')
            ->when($groupId, fn($q) => $q->where('rg.grupa_id', $groupId))
            ->select('p.id', 'p.name')
            ->distinct()
            ->orderBy('p.name')
            ->get();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(
        path: '/schedule/groups',
        summary: 'Список груп згрупованих по факультетах',
        description: 'Повертає всі групи інституту, згруповані по факультетах і курсах.',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        responses: [
            new OA\Response(response: 200, description: 'Масив факультетів з групами'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function groups(): JsonResponse
    {
        $rows = DB::connection('mysql')
            ->table('asu_grupa as g')
            ->leftJoin('asu_faculty as f', 'f.id', '=', 'g.asu_faculty_id')
            ->where('g.archive', 0)
            ->select('g.id', 'g.name', 'g.course', 'f.id as faculty_id', 'f.name as faculty_name')
            ->orderBy('f.name')
            ->orderBy('g.course')
            ->orderBy('g.name')
            ->get();

        $grouped = $rows->groupBy('faculty_id')->map(function ($groups, $facultyId) {
            $first = $groups->first();
            return [
                'faculty_id'   => $facultyId ? (int) $facultyId : null,
                'faculty_name' => $first->faculty_name ?? 'Без факультету',
                'groups'       => $groups->map(fn($g) => [
                    'id'     => $g->id,
                    'name'   => $g->name,
                    'course' => $g->course,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    #[OA\Get(
        path: '/schedule/lesson-types',
        summary: 'Список типів занять',
        description: 'Повертає всі можливі типи занять з ключами для фільтрації.',
        security: [['BearerAuth' => []]],
        tags: ['Schedule'],
        responses: [
            new OA\Response(response: 200, description: 'Список типів занять'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function lessonTypes(): JsonResponse
    {
        $types = [
            ['key' => 'lecturing_hours',              'label' => 'Лекція'],
            ['key' => 'practical_hours',              'label' => 'Практика'],
            ['key' => 'lab_hours',                    'label' => 'Лабораторна'],
            ['key' => 'seminar_hours',                'label' => 'Семінар'],
            ['key' => 'consult_hours',                'label' => 'Консультація'],
            ['key' => 'individual_hours',             'label' => 'Індивідуальна'],
            ['key' => 'labor_hours',                  'label' => 'Самостійна'],
            ['key' => 'zalik_hours',                  'label' => 'Залік'],
            ['key' => 'semester_exams_hours',         'label' => 'Екзамен'],
            ['key' => 'certification_exams_hours',    'label' => 'Атестація'],
            ['key' => 'coursework_protection_hours',  'label' => 'Захист курсової'],
        ];

        return response()->json(['data' => $types]);
    }

    private function activeGroupId(Request $request): ?int
    {
        $student = $request->user();
        if (!$student) {
            return null;
        }

        $group = DB::connection('mysql')
            ->table('asu_grupa_student')
            ->where('student_id', $student->id)
            ->where('archive', 0)
            ->when($student->cb_number, fn($q) => $q->where('cb_number', $student->cb_number))
            ->value('grupa_id');

        return $group ? (int) $group : null;
    }
}
