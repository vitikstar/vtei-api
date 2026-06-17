<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class GradeController extends Controller
{
    // m10-m29 — модуль 1, m30-m49 — модуль 2
    private array $mod1Keys = ['m10','m11','m12','m13','m14','m15','m16','m17','m18','m19','m20','m21','m22','m23','m24','m25','m26','m27','m28','m29'];
    private array $mod2Keys = ['m30','m31','m32','m33','m34','m35','m36','m37','m38','m39','m40','m41','m42','m43','m44','m45','m46','m47','m48','m49'];

    #[OA\Get(
        path: '/grades',
        summary: 'Успішність по семестрах',
        description: 'Повертає модульні оцінки та результати екзаменів/заліків, згруповані по навчальних роках і семестрах.',
        security: [['BearerAuth' => []]],
        tags: ['Grades'],
        responses: [
            new OA\Response(response: 200, description: 'Успішність згрупована по роках і семестрах'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $modRows = DB::connection('mysql_s')
            ->table('mod_list as ml')
            ->join('mod_cards as mc', 'mc.id', '=', 'ml.mod_card_id')
            ->join('subjects as s', 's.id', '=', 'mc.subject')
            ->where('ml.stud_id', $student->id)
            ->select(
                'ml.mod_card_id',
                'ml.sum1', 'ml.mark1', 'ml.sum2', 'ml.mark2',
                'ml.total', 'ml.ects',
                'mc.sem', 'mc.teach_year', 'mc.is_exam', 'mc.credits',
                's.id as subject_id', 's.subject as subject_name',
            )
            ->orderBy('mc.teach_year')
            ->orderBy('mc.sem')
            ->get();

        $examRows = DB::connection('mysql_s')
            ->table('dec_exam as de')
            ->join('dec_exam_header as deh', 'deh.id', '=', 'de.head_id')
            ->where('de.stud_id', $student->id)
            ->select('de.score', 'de.mark', 'de.extra_points', 'deh.subj_id', 'deh.sem_num', 'deh.year')
            ->get()
            ->keyBy(fn($r) => $r->subj_id . '_' . $r->year . '_' . $r->sem_num);

        $data = $modRows
            ->groupBy('teach_year')
            ->map(function ($yearRows, $year) use ($examRows) {
                $sems = $yearRows->groupBy('sem')->map(function ($semRows, $sem) use ($year, $examRows) {
                    $subjects = $semRows->map(function ($row) use ($year, $examRows) {
                        $exam = $examRows->get($row->subject_id . '_' . $year . '_' . $row->sem);
                        return [
                            'card_id'      => $row->mod_card_id,
                            'subject_id'   => $row->subject_id,
                            'subject_name' => trim($row->subject_name),
                            'credits'      => $row->credits,
                            'is_exam'      => (bool) $row->is_exam,
                            'mod1'         => ['sum' => $row->sum1, 'mark' => $row->mark1],
                            'mod2'         => ['sum' => $row->sum2, 'mark' => $row->mark2],
                            'total'        => $row->total,
                            'ects'         => $row->ects,
                            'exam'         => $exam ? [
                                'score'        => $exam->score,
                                'mark'         => $exam->mark,
                                'mark_label'   => $this->markLabel($exam->mark),
                                'extra_points' => $exam->extra_points,
                            ] : null,
                        ];
                    })->values();

                    return ['semester' => (int) $sem, 'subjects' => $subjects];
                })->sortKeys()->values();

                return ['year' => $year, 'semesters' => $sems];
            })
            ->sortKeys()
            ->values();

        return response()->json(['data' => $data]);
    }

    #[OA\Get(
        path: '/grades/modules',
        summary: 'Модульні картки поточного року',
        description: 'Повертає модульні картки студента за поточний навчальний рік.',
        security: [['BearerAuth' => []]],
        tags: ['Grades'],
        responses: [
            new OA\Response(response: 200, description: 'Список карток'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function modules(Request $request): JsonResponse
    {
        $student = $request->user();
        $year    = $this->currentYear();

        $rows = DB::connection('mysql_s')
            ->table('mod_list as ml')
            ->join('mod_cards as mc', 'mc.id', '=', 'ml.mod_card_id')
            ->join('subjects as s', 's.id', '=', 'mc.subject')
            ->where('ml.stud_id', $student->id)
            ->where('mc.teach_year', $year)
            ->select(
                'ml.mod_card_id as card_id',
                'ml.sum1', 'ml.mark1', 'ml.sum2', 'ml.mark2',
                'ml.total', 'ml.ects',
                'mc.sem', 'mc.is_exam', 'mc.credits', 'mc.max1', 'mc.max2',
                's.id as subject_id', 's.subject as subject_name',
            )
            ->orderBy('mc.sem')
            ->orderBy('s.subject')
            ->get();

        $data = $rows->map(fn($r) => [
            'card_id'      => $r->card_id,
            'subject_id'   => $r->subject_id,
            'subject_name' => trim($r->subject_name),
            'semester'     => (int) $r->sem,
            'credits'      => $r->credits,
            'is_exam'      => (bool) $r->is_exam,
            'mod1'         => ['sum' => $r->sum1, 'max' => $r->max1, 'mark' => $r->mark1],
            'mod2'         => ['sum' => $r->sum2, 'max' => $r->max2, 'mark' => $r->mark2],
            'total'        => $r->total,
            'ects'         => $r->ects,
        ]);

        return response()->json(['year' => $year, 'data' => $data]);
    }

    #[OA\Get(
        path: '/grades/modules/{card_id}',
        summary: 'Деталі модульної картки',
        description: 'Повертає розбивку балів по окремих завданнях кожного модуля.',
        security: [['BearerAuth' => []]],
        tags: ['Grades'],
        parameters: [
            new OA\Parameter(name: 'card_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Деталі картки'),
            new OA\Response(response: 404, description: 'Не знайдено'),
        ]
    )]
    public function moduleDetail(Request $request, int $card_id): JsonResponse
    {
        $student = $request->user();

        $card = DB::connection('mysql_s')
            ->table('mod_cards as mc')
            ->join('subjects as s', 's.id', '=', 'mc.subject')
            ->where('mc.id', $card_id)
            ->select('mc.*', 's.subject as subject_name')
            ->first();

        if (!$card) {
            return response()->json(['message' => 'Картку не знайдено'], 404);
        }

        $grade = DB::connection('mysql_s')
            ->table('mod_list')
            ->where('mod_card_id', $card_id)
            ->where('stud_id', $student->id)
            ->first();

        if (!$grade) {
            return response()->json(['message' => 'Оцінку не знайдено'], 404);
        }

        return response()->json([
            'card_id'      => $card_id,
            'subject_name' => trim($card->subject_name),
            'semester'     => (int) $card->sem,
            'teach_year'   => $card->teach_year,
            'is_exam'      => (bool) $card->is_exam,
            'credits'      => $card->credits,
            'mod1' => [
                'sum'   => $grade->sum1,
                'mark'  => $grade->mark1,
                'max'   => $card->max1,
                'tasks' => $this->parseTasks($card, $grade, $this->mod1Keys),
            ],
            'mod2' => [
                'sum'   => $grade->sum2,
                'mark'  => $grade->mark2,
                'max'   => $card->max2,
                'tasks' => $this->parseTasks($card, $grade, $this->mod2Keys),
            ],
            'total' => $grade->total,
            'ects'  => $grade->ects,
        ]);
    }

    #[OA\Get(
        path: '/grades/markbook',
        summary: 'Залікова книжка',
        description: 'Всі результати екзаменів та заліків, згруповані по навчальних роках і семестрах.',
        security: [['BearerAuth' => []]],
        tags: ['Grades'],
        responses: [
            new OA\Response(response: 200, description: 'Залікова книжка'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function markbook(Request $request): JsonResponse
    {
        $student = $request->user();

        $rows = DB::connection('mysql_s')
            ->table('dec_exam as de')
            ->join('dec_exam_header as deh', 'deh.id', '=', 'de.head_id')
            ->join('subjects as s', 's.id', '=', 'deh.subj_id')
            ->where('de.stud_id', $student->id)
            ->select(
                'de.score', 'de.mark', 'de.mod_one', 'de.mod_two', 'de.extra_points',
                'deh.year', 'deh.sem_num', 'deh.is_exam', 'deh.credits', 'deh.date as exam_date',
                's.id as subject_id', 's.subject as subject_name',
            )
            ->orderBy('deh.year')
            ->orderBy('deh.sem_num')
            ->get();

        $data = $rows->groupBy('year')->map(function ($yearRows, $year) {
            $sems = $yearRows->groupBy('sem_num')->map(function ($semRows, $sem) {
                return [
                    'semester' => (int) $sem,
                    'subjects' => $semRows->map(fn($r) => [
                        'subject_id'   => $r->subject_id,
                        'subject_name' => trim($r->subject_name),
                        'credits'      => $r->credits,
                        'is_exam'      => (bool) $r->is_exam,
                        'mod1'         => $r->mod_one,
                        'mod2'         => $r->mod_two,
                        'extra_points' => $r->extra_points,
                        'score'        => $r->score,
                        'mark'         => $r->mark,
                        'mark_label'   => $this->markLabel($r->mark),
                        'exam_date'    => $r->exam_date,
                    ])->values(),
                ];
            })->sortKeys()->values();

            return ['year' => $year, 'semesters' => $sems];
        })->sortKeys()->values();

        return response()->json(['data' => $data]);
    }

    // --- Приватні ---

    private function parseTasks(object $card, object $grade, array $keys): array
    {
        $tasks = [];
        foreach ($keys as $key) {
            $taskDef = $card->$key ?? '';
            if (empty($taskDef)) {
                continue;
            }
            // Формат в mod_cards: "a1~Назва завдання~" або просто назва
            $parts  = explode('~', $taskDef);
            $name   = count($parts) >= 2 ? trim($parts[1]) : trim($taskDef);
            $score  = $grade->$key ?? 0;
            $tasks[] = ['name' => $name, 'score' => $score];
        }
        return $tasks;
    }

    private function markLabel(mixed $mark): string
    {
        return match ((int) $mark) {
            5 => 'Відмінно',
            4 => 'Добре',
            3 => 'Задовільно',
            2 => 'Незадовільно',
            1 => 'Незараховано',
            default => '—',
        };
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
