<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class SettingsController extends Controller
{
    #[OA\Get(
        path: '/settings',
        summary: 'Налаштування студента',
        description: 'Повертає поточні налаштування: FCM токен, Telegram.',
        security: [['BearerAuth' => []]],
        tags: ['Settings'],
        responses: [
            new OA\Response(response: 200, description: 'Налаштування'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        return response()->json([
            'notifications' => [
                'fcm_enabled' => !empty($student->fcm_token),
                'fcm_token'   => $student->fcm_token,
            ],
            'telegram' => [
                'username' => $student->tg_username ?? null,
            ],
        ]);
    }

    #[OA\Put(
        path: '/settings/notifications',
        summary: 'Оновити FCM токен',
        description: 'Зберігає або оновлює FCM токен для push-нотифікацій. Передати null щоб відключити.',
        security: [['BearerAuth' => []]],
        tags: ['Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'fcm_token', type: 'string', nullable: true, description: 'FCM токен пристрою або null'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Оновлено'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function notifications(Request $request): JsonResponse
    {
        $request->validate([
            'fcm_token' => 'nullable|string|max:512',
        ]);

        DB::connection('mysql')
            ->table('users_student')
            ->where('id', $request->user()->id)
            ->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'Оновлено']);
    }
}
