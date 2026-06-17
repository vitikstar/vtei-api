<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class FaqController extends Controller
{
    #[OA\Get(
        path: '/faq',
        summary: 'Групи FAQ',
        description: 'Повертає список груп питань з кількістю питань у кожній.',
        tags: ['FAQ'],
        responses: [
            new OA\Response(response: 200, description: 'Список груп FAQ'),
        ]
    )]
    public function index(): JsonResponse
    {
        $groups = DB::connection('mysql')
            ->table('faq_groups as g')
            ->leftJoin('faq_qa as q', 'q.group_id', '=', 'g.id')
            ->select('g.id', 'g.name', DB::raw('count(q.id) as questions_count'))
            ->groupBy('g.id', 'g.name')
            ->orderBy('g.id')
            ->get();

        return response()->json(['data' => $groups]);
    }

    #[OA\Get(
        path: '/faq/{group_id}/questions',
        summary: 'Питання групи FAQ',
        description: 'Повертає всі питання і відповіді вказаної групи.',
        tags: ['FAQ'],
        parameters: [
            new OA\Parameter(name: 'group_id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список питань'),
            new OA\Response(response: 404, description: 'Групу не знайдено'),
        ]
    )]
    public function questions(int $group_id): JsonResponse
    {
        $group = DB::connection('mysql')->table('faq_groups')->where('id', $group_id)->first();
        if (!$group) {
            return response()->json(['message' => 'Групу не знайдено'], 404);
        }

        $questions = DB::connection('mysql')
            ->table('faq_qa')
            ->where('group_id', $group_id)
            ->orderByDesc('popular')
            ->orderBy('id')
            ->get(['id', 'question', 'answer', 'popular']);

        $data = $questions->map(fn($q) => [
            'id'       => $q->id,
            'question' => strip_tags($q->question),
            'answer'   => $q->answer && !str_contains($q->answer, 'not-set')
                ? strip_tags($q->answer)
                : null,
            'popular'  => (bool) $q->popular,
        ]);

        return response()->json([
            'group' => ['id' => $group->id, 'name' => $group->name],
            'data'  => $data,
        ]);
    }

    #[OA\Post(
        path: '/faq/question',
        summary: 'Задати питання',
        description: 'Додає нове питання у вказану групу FAQ.',
        tags: ['FAQ'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['question', 'group_id'],
                properties: [
                    new OA\Property(property: 'question', type: 'string', description: 'Текст питання'),
                    new OA\Property(property: 'group_id', type: 'integer', description: 'ID групи'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Питання додано'),
            new OA\Response(response: 422, description: 'Помилка валідації'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|min:5|max:1000',
            'group_id' => 'required|integer|exists:mysql.faq_groups,id',
        ]);

        $id = DB::connection('mysql')->table('faq_qa')->insertGetId([
            'question'   => $request->question,
            'answer'     => null,
            'group_id'   => $request->group_id,
            'popular'    => 0,
            'read_admin' => 0,
            'created_at' => now()->timestamp,
            'updated_at' => now()->timestamp,
        ]);

        return response()->json(['message' => 'Питання додано', 'id' => $id], 201);
    }
}
