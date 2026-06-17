<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DisciplineController extends Controller
{
    #[OA\Get(
        path: '/disciplines/electives',
        summary: 'Список вибіркових дисциплін',
        description: 'Повертає доступні вибіркові дисципліни для освітньої програми і курсу студента. Включає інформацію про поточну/наступну хвилю вибору та кількість студентів що обрали кожну дисципліну.',
        security: [['BearerAuth' => []]],
        tags: ['Disciplines'],
        responses: [
            new OA\Response(response: 200, description: 'Список вибіркових дисциплін з інфо про хвилю'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function electives(Request $request): JsonResponse
    {
        $student  = $request->user();
        $year     = $this->currentYear();
        [$eduProgramId, $course, $grupId] = $this->studentContext($student->id);

        // Поточна або наступна хвиля вибору
        $now       = now();
        $waveInfo  = $this->resolveWave($now, $year);

        // Вибіркові доступні для освітньої програми студента
        $disciplines = DB::connection('mysql')
            ->table('asu_predmet as p')
            ->leftJoin('asu_predmet_edu_program as pep', function ($j) use ($eduProgramId, $course) {
                $j->on('pep.predmet_id', '=', 'p.id')
                  ->where('pep.edu_program_id', $eduProgramId)
                  ->where('pep.kurs', $course);
            })
            ->leftJoin('asu_kafedra as k', 'k.id', '=', 'p.kafedra_id')
            ->where('p.show', 1)
            ->where('p.archive', 0)
            ->where(function ($q) use ($eduProgramId) {
                // Або прив'язаний до освітньої програми, або загальний (без прив'язки)
                $q->whereNotNull('pep.predmet_id')
                  ->orWhere('p.general_list', 1);
            })
            ->select(
                'p.id', 'p.name', 'p.name_en', 'p.exam',
                'p.min_count_stud_form_group', 'p.max_count_stud_form_group',
                'p.file_name_1', 'p.file_name_2', 'p.file_name_3',
                'k.id as kafedra_id', 'k.name as kafedra_name',
            )
            ->orderBy('p.name')
            ->get();

        // Кількість обравших кожну дисципліну в поточному році
        $selectionCounts = DB::connection('mysql')
            ->table('nv_predmet_student_stage')
            ->where('edu_year', $year)
            ->select('predmet_id', DB::raw('count(*) as cnt'))
            ->groupBy('predmet_id')
            ->pluck('cnt', 'predmet_id');

        // Які дисципліни вже обрав цей студент
        $selectedIds = DB::connection('mysql')
            ->table('nv_predmet_student_stage')
            ->where('student_id', $student->id)
            ->where('edu_year', $year)
            ->pluck('predmet_id')
            ->flip();

        $data = $disciplines->map(fn($d) => [
            'id'             => $d->id,
            'name'           => $d->name,
            'name_en'        => $d->name_en,
            'has_exam'       => $d->exam === '1',
            'kafedra_id'     => $d->kafedra_id,
            'kafedra_name'   => $d->kafedra_name,
            'min_students'   => $d->min_count_stud_form_group,
            'max_students'   => $d->max_count_stud_form_group,
            'selected_count' => (int) ($selectionCounts[$d->id] ?? 0),
            'is_selected'    => isset($selectedIds[$d->id]),
            'syllabuses'     => $this->syllabusUrls($d),
        ]);

        return response()->json([
            'year'       => $year,
            'wave'       => $waveInfo,
            'data'       => $data,
        ]);
    }

    #[OA\Get(
        path: '/disciplines/electives/selected',
        summary: 'Обрані вибіркові дисципліни',
        description: 'Повертає дисципліни, які студент обрав у поточному навчальному році.',
        security: [['BearerAuth' => []]],
        tags: ['Disciplines'],
        responses: [
            new OA\Response(response: 200, description: 'Обрані дисципліни'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function selected(Request $request): JsonResponse
    {
        $student = $request->user();
        $year    = $this->currentYear();

        $rows = DB::connection('mysql')
            ->table('nv_predmet_student_stage as ps')
            ->join('asu_predmet as p', 'p.id', '=', 'ps.predmet_id')
            ->leftJoin('asu_kafedra as k', 'k.id', '=', 'p.kafedra_id')
            ->where('ps.student_id', $student->id)
            ->where('ps.edu_year', $year)
            ->select(
                'ps.id', 'ps.predmet_id', 'ps.sem', 'ps.hold',
                'ps.number_stage', 'ps.time_create',
                'p.name', 'p.name_en', 'p.exam',
                'k.id as kafedra_id', 'k.name as kafedra_name',
            )
            ->orderBy('ps.sem')
            ->orderBy('p.name')
            ->get();

        $data = $rows->map(fn($r) => [
            'id'           => $r->id,
            'predmet_id'   => $r->predmet_id,
            'name'         => $r->name,
            'name_en'      => $r->name_en,
            'has_exam'     => $r->exam === '1',
            'semester'     => $r->sem,
            'stage'        => $r->number_stage,
            'confirmed'    => (bool) $r->hold,
            'kafedra_id'   => $r->kafedra_id,
            'kafedra_name' => $r->kafedra_name,
            'selected_at'  => $r->time_create,
        ]);

        return response()->json(['year' => $year, 'data' => $data]);
    }

    #[OA\Post(
        path: '/disciplines/electives/save',
        summary: 'Зберегти вибір дисциплін',
        description: 'Зберігає вибрані студентом вибіркові дисципліни. Перезаписує поточний вибір для вказаного семестру.',
        security: [['BearerAuth' => []]],
        tags: ['Disciplines'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['predmet_ids', 'sem'],
                properties: [
                    new OA\Property(property: 'predmet_ids', type: 'array', items: new OA\Items(type: 'integer'), description: 'ID дисциплін'),
                    new OA\Property(property: 'sem', type: 'integer', description: 'Семестр (1 або 2)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Вибір збережено'),
            new OA\Response(response: 400, description: 'Вибір зараз недоступний'),
            new OA\Response(response: 422, description: 'Помилка валідації'),
        ]
    )]
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'predmet_ids'   => 'required|array|min:1',
            'predmet_ids.*' => 'integer',
            'sem'           => 'required|integer|in:1,2',
        ]);

        $student  = $request->user();
        $year     = $this->currentYear();
        $wave     = $this->resolveWave(now(), $year);

        if (!$wave || $wave['status'] !== 'active') {
            return response()->json(['message' => 'Вибір дисциплін зараз недоступний'], 400);
        }

        [, , $grupId] = $this->studentContext($student->id);
        $cbNumber = DB::connection('mysql')
            ->table('asu_grupa_student')
            ->where('student_id', $student->id)
            ->where('archive', 0)
            ->value('cb_number');

        // Видалити старий вибір для цього семестру і хвилі
        DB::connection('mysql')
            ->table('nv_predmet_student_stage')
            ->where('student_id', $student->id)
            ->where('edu_year', $year)
            ->where('sem', $request->sem)
            ->delete();

        $now  = now()->toDateTimeString();
        $rows = collect($request->predmet_ids)->map(fn($predmetId) => [
            'student_id'   => $student->id,
            'predmet_id'   => $predmetId,
            'cb_number'    => $cbNumber,
            'edu_year'     => $year,
            'sem'          => $request->sem,
            'number_stage' => $wave['stage'],
            'hold'         => 0,
            'grupa_id'     => $grupId,
            'general_list' => 0,
            'time_create'  => $now,
        ])->all();

        DB::connection('mysql')->table('nv_predmet_student_stage')->insert($rows);

        return response()->json(['message' => 'Вибір збережено', 'count' => count($rows)]);
    }

    #[OA\Delete(
        path: '/disciplines/electives/{id}',
        summary: 'Видалити вибіркову дисципліну',
        description: 'Видаляє одну дисципліну з вибору студента.',
        security: [['BearerAuth' => []]],
        tags: ['Disciplines'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID запису з nv_predmet_student_stage', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Видалено'),
            new OA\Response(response: 404, description: 'Не знайдено'),
        ]
    )]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = DB::connection('mysql')
            ->table('nv_predmet_student_stage')
            ->where('id', $id)
            ->where('student_id', $request->user()->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Не знайдено'], 404);
        }

        return response()->json(['message' => 'Видалено']);
    }

    #[OA\Get(
        path: '/disciplines/history',
        summary: 'Історія дисциплін',
        description: 'Всі дисципліни студента по навчальних роках (обов\'язкові + вибіркові).',
        security: [['BearerAuth' => []]],
        tags: ['Disciplines'],
        responses: [
            new OA\Response(response: 200, description: 'Дисципліни по роках'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function history(Request $request): JsonResponse
    {
        $student = $request->user();

        $rows = DB::connection('mysql')
            ->table('nv_predmet_student as ps')
            ->join('asu_predmet as p', 'p.id', '=', 'ps.predmet_id')
            ->leftJoin('asu_kafedra as k', 'k.id', '=', 'p.kafedra_id')
            ->where('ps.student_id', $student->id)
            ->select(
                'ps.year', 'ps.kurs',
                'p.id as predmet_id', 'p.name', 'p.name_en', 'p.exam',
                'k.id as kafedra_id', 'k.name as kafedra_name',
            )
            ->orderBy('ps.year')
            ->orderBy('p.name')
            ->get();

        $data = $rows->groupBy('year')->map(function ($yearRows, $year) {
            return [
                'year'        => $year,
                'course'      => $yearRows->first()->kurs,
                'disciplines' => $yearRows->map(fn($r) => [
                    'predmet_id'   => $r->predmet_id,
                    'name'         => $r->name,
                    'name_en'      => $r->name_en,
                    'has_exam'     => $r->exam === '1',
                    'kafedra_id'   => $r->kafedra_id,
                    'kafedra_name' => $r->kafedra_name,
                ])->values(),
            ];
        })->sortKeys()->values();

        return response()->json(['data' => $data]);
    }

    // --- Приватні ---

    private function studentContext(int $studentId): array
    {
        $groupRow = DB::connection('mysql')
            ->table('asu_grupa_student')
            ->where('student_id', $studentId)
            ->where('archive', 0)
            ->first();

        $grupa = $groupRow
            ? DB::connection('mysql')->table('asu_grupa')->where('id', $groupRow->grupa_id)->first()
            : null;

        return [
            $grupa?->asu_educational_program_id,
            $grupa?->course,
            $groupRow?->grupa_id,
        ];
    }

    private function resolveWave(\Illuminate\Support\Carbon $now, string $year): ?array
    {
        $active = DB::connection('mysql')
            ->table('asu_setting_vb')
            ->where('year_edu', $year)
            ->where('time_start', '<=', $now)
            ->where('time_end', '>=', $now)
            ->first();

        if ($active) {
            return [
                'status'     => 'active',
                'stage'      => $active->num_stage,
                'name'       => $active->name,
                'time_start' => $active->time_start,
                'time_end'   => $active->time_end,
            ];
        }

        $next = DB::connection('mysql')
            ->table('asu_setting_vb')
            ->where('year_edu', $year)
            ->where('time_start', '>', $now)
            ->orderBy('time_start')
            ->first();

        if ($next) {
            return [
                'status'     => 'upcoming',
                'stage'      => $next->num_stage,
                'name'       => $next->name,
                'time_start' => $next->time_start,
                'time_end'   => $next->time_end,
            ];
        }

        return null;
    }

    private function syllabusUrls(object $discipline): array
    {
        $urls = [];
        foreach (['file_name_1', 'file_name_2', 'file_name_3'] as $field) {
            if (!empty($discipline->$field)) {
                $urls[] = 'https://cabinet.vtei.edu.ua/uploads/elective/' . $discipline->$field;
            }
        }
        return $urls;
    }

    private function currentYear(): string
    {
        $year  = now()->year;
        $month = now()->month;
        return $month >= 9
            ? $year . '-' . ($year + 1)
            : ($year - 1) . '-' . $year;
    }
}
