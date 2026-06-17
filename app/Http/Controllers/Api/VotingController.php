<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class VotingController extends Controller
{
    #[OA\Get(
        path: '/voting',
        summary: 'Кандидати голосування',
        description: 'Повертає список кандидатів студентського самоврядування та чи вже проголосував студент.',
        security: [['BearerAuth' => []]],
        tags: ['Voting'],
        responses: [
            new OA\Response(response: 200, description: 'Кандидати і статус голосування'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $candidates = DB::connection('mysql')
            ->table('rss_candidate as c')
            ->leftJoin('users_student as s', 's.id', '=', 'c.student_grupa_id')
            ->select('c.id', 'c.student_grupa_id', 'c.name', 'c.program', 'c.photo', 's.name as student_name')
            ->get();

        $voted = DB::connection('mysql')
            ->table('rss_voted_head')
            ->where('voted_student_id', $student->id)
            ->first();

        $data = $candidates->map(fn($c) => [
            'id'          => $c->id,
            'name'        => $c->name ?? $c->student_name,
            'program'     => strip_tags($c->program ?? ''),
            'photo_url'   => $c->photo ? 'https://cabinet.vtei.edu.ua/uploads/rss/' . $c->photo : null,
            'student_id'  => $c->student_grupa_id,
        ]);

        return response()->json([
            'data'      => $data,
            'has_voted' => (bool) $voted,
            'voted_for' => $voted?->candidate_student_id,
        ]);
    }

    #[OA\Post(
        path: '/voting/vote',
        summary: 'Проголосувати',
        description: 'Записує голос студента за кандидата. Повторне голосування неможливе.',
        security: [['BearerAuth' => []]],
        tags: ['Voting'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['candidate_student_id'],
                properties: [
                    new OA\Property(property: 'candidate_student_id', type: 'integer', description: 'student_grupa_id кандидата'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Голос зараховано'),
            new OA\Response(response: 409, description: 'Вже проголосував'),
            new OA\Response(response: 422, description: 'Помилка валідації'),
        ]
    )]
    public function vote(Request $request): JsonResponse
    {
        $request->validate([
            'candidate_student_id' => 'required|integer|exists:mysql.rss_candidate,student_grupa_id',
        ]);

        $student = $request->user();

        $alreadyVoted = DB::connection('mysql')
            ->table('rss_voted_head')
            ->where('voted_student_id', $student->id)
            ->exists();

        if ($alreadyVoted) {
            return response()->json(['message' => 'Ви вже проголосували'], 409);
        }

        DB::connection('mysql')->table('rss_voted_head')->insert([
            'voted_student_id'     => $student->id,
            'candidate_student_id' => $request->candidate_student_id,
            'voting_time'          => now(),
            'type_voted'           => 'all',
            'data_voted'           => '',
        ]);

        return response()->json(['message' => 'Голос зараховано']);
    }

    #[OA\Get(
        path: '/voting/results',
        summary: 'Результати голосування',
        description: 'Повертає кількість голосів за кожного кандидата.',
        security: [['BearerAuth' => []]],
        tags: ['Voting'],
        responses: [
            new OA\Response(response: 200, description: 'Результати'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function results(Request $request): JsonResponse
    {
        $totalVotes = DB::connection('mysql')->table('rss_voted_head')->count();

        $candidates = DB::connection('mysql')
            ->table('rss_candidate as c')
            ->leftJoin('users_student as s', 's.id', '=', 'c.student_grupa_id')
            ->leftJoin(DB::raw('(SELECT candidate_student_id, count(*) as votes FROM rss_voted_head GROUP BY candidate_student_id) as v'), 'v.candidate_student_id', '=', 'c.student_grupa_id')
            ->select('c.id', 'c.student_grupa_id', 'c.name', 'c.photo', 's.name as student_name', DB::raw('COALESCE(v.votes, 0) as votes'))
            ->orderByDesc('votes')
            ->get();

        $data = $candidates->map(fn($c) => [
            'id'         => $c->id,
            'name'       => $c->name ?? $c->student_name,
            'photo_url'  => $c->photo ? 'https://cabinet.vtei.edu.ua/uploads/rss/' . $c->photo : null,
            'votes'      => (int) $c->votes,
            'percentage' => $totalVotes > 0 ? round($c->votes / $totalVotes * 100, 1) : 0,
        ]);

        return response()->json([
            'total_votes' => $totalVotes,
            'data'        => $data,
        ]);
    }
}
