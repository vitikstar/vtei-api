<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\StudentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/auth/login',
        summary: 'Вхід студента',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: 'nastyastorozhuk10@gmail.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Успішний вхід'),
            new OA\Response(response: 422, description: 'Невірний логін або пароль'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $student = Student::where('email', $request->login)->first();

        if (!$student || !Hash::check($request->password, $student->pwd)) {
            throw ValidationException::withMessages([
                'login' => ['Невірний логін або пароль.'],
            ]);
        }

        if (!$student->isActive()) {
            throw ValidationException::withMessages([
                'login' => ['Акаунт не активований.'],
            ]);
        }

        $groups = StudentGroup::where('student_id', $student->id)
            ->where('archive', 0)
            ->get();

        $token = $student->createToken('mobile')->plainTextToken;

        // Якщо є збережена активна група — залишаємо її, інакше ставимо першу
        $hasActive = DB::table('student_active_groups')
            ->where('student_id', $student->id)
            ->exists();

        if (!$hasActive && $groups->isNotEmpty()) {
            $first = $groups->first();
            DB::table('student_active_groups')->insertOrIgnore([
                'student_id' => $student->id,
                'cb_number'  => $first->cb_number,
                'grupa_id'   => $first->grupa_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'token'            => $token,
            'needs_group_select' => $groups->count() > 1,
            'student'          => new StudentResource($student),
            'groups'           => $groups->count() > 1
                ? $groups->map(fn($g) => [
                    'cb_number'  => $g->cb_number,
                    'grupa_id'   => $g->grupa_id,
                ])
                : null,
        ]);
    }

    #[OA\Put(
        path: '/auth/select-group',
        summary: 'Встановити активну групу (для студентів у кількох групах)',
        security: [['BearerAuth' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cb_number'],
                properties: [
                    new OA\Property(property: 'cb_number', type: 'string', example: '272062025'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Активну групу встановлено'),
            new OA\Response(response: 404, description: 'Групу не знайдено'),
        ]
    )]
    public function selectGroup(Request $request): JsonResponse
    {
        $request->validate([
            'cb_number' => 'required|string',
        ]);

        $student = $request->user();

        $group = StudentGroup::where('student_id', $student->id)
            ->where('cb_number', $request->cb_number)
            ->where('archive', 0)
            ->first();

        if (!$group) {
            return response()->json(['message' => 'Групу не знайдено'], 404);
        }

        DB::table('student_active_groups')->updateOrInsert(
            ['student_id' => $student->id],
            [
                'cb_number'  => $group->cb_number,
                'grupa_id'   => $group->grupa_id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message'  => 'Активну групу встановлено',
            'cb_number' => $group->cb_number,
            'grupa_id'  => $group->grupa_id,
        ]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Вихід з системи',
        security: [['BearerAuth' => []]],
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'Успішний вихід')]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вихід виконано']);
    }

    #[OA\Post(
        path: '/auth/recovery',
        summary: 'Запит на відновлення паролю',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [new OA\Property(property: 'email', type: 'string', format: 'email')]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Email надіслано')]
    )]
    public function recovery(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // TODO: надіслати email з токеном відновлення

        return response()->json(['message' => 'Якщо email існує — лист надіслано']);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'Встановити новий пароль за токеном',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['access_token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'access_token', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6),
                    new OA\Property(property: 'password_confirmation', type: 'string'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Пароль змінено')]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $student = Student::where('access_token', $request->access_token)->first();

        if (!$student) {
            return response()->json(['message' => 'Невалідний токен'], 422);
        }

        $student->update(['pwd' => Hash::make($request->password)]);

        return response()->json(['message' => 'Пароль успішно змінено']);
    }
}
