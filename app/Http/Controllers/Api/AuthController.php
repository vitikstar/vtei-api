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
        summary: 'Вхід по email або заліковій книжці (cb_number)',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['login', 'password'],
                properties: [
                    new OA\Property(property: 'login', type: 'string', example: '272062025', description: 'Номер залікової книжки'),
                    new OA\Property(property: 'password', type: 'string', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Успішний вхід. Якщо needs_group_select=true — показати список groups для вибору, потім PUT /auth/select-group'),
            new OA\Response(response: 422, description: 'Невірний логін або пароль'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $login = $request->login;

        // Спробуємо знайти по cb_number
        $groupRow = DB::connection('mysql')
            ->table('asu_grupa_student')
            ->where('cb_number', $login)
            ->where('archive', 0)
            ->first();

        if ($groupRow) {
            // Логін по заліковій — студент і група відомі одразу
            $student = Student::find($groupRow->student_id);

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

            // Група відома — зберігаємо в токені
            $token = $student->createToken('mobile', [
                'grupa_id:' . $groupRow->grupa_id,
                'cb_number:' . $groupRow->cb_number,
            ])->plainTextToken;

            return response()->json([
                'token'              => $token,
                'needs_group_select' => false,
                'student'            => new StudentResource($student),
                'group'              => [
                    'cb_number' => $groupRow->cb_number,
                    'grupa_id'  => $groupRow->grupa_id,
                ],
            ]);
        }

        // Логін по email
        $student = Student::where('email', $login)->first();

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

        if ($groups->count() === 1) {
            $g = $groups->first();
            $token = $student->createToken('mobile', [
                'grupa_id:' . $g->grupa_id,
                'cb_number:' . $g->cb_number,
            ])->plainTextToken;

            return response()->json([
                'token'              => $token,
                'needs_group_select' => false,
                'student'            => new StudentResource($student),
                'group'              => [
                    'cb_number' => $g->cb_number,
                    'grupa_id'  => $g->grupa_id,
                ],
            ]);
        }

        // Кілька груп — повертаємо тимчасовий токен без групи, студент має вибрати
        $token = $student->createToken('mobile-pending')->plainTextToken;

        return response()->json([
            'token'              => $token,
            'needs_group_select' => true,
            'student'            => new StudentResource($student),
            'groups'             => $groups->map(fn($g) => [
                'cb_number' => $g->cb_number,
                'grupa_id'  => $g->grupa_id,
            ]),
        ]);
    }

    #[OA\Put(
        path: '/auth/select-group',
        summary: 'Вибрати активну групу (якщо needs_group_select=true). Повертає новий токен з групою.',
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
            new OA\Response(response: 200, description: 'Новий токен з групою'),
            new OA\Response(response: 404, description: 'Групу не знайдено'),
        ]
    )]
    public function selectGroup(Request $request): JsonResponse
    {
        $request->validate(['cb_number' => 'required|string']);

        $student = $request->user();

        $group = StudentGroup::where('student_id', $student->id)
            ->where('cb_number', $request->cb_number)
            ->where('archive', 0)
            ->first();

        if (!$group) {
            return response()->json(['message' => 'Групу не знайдено'], 404);
        }

        // Видаляємо тимчасовий токен, видаємо новий з групою
        $request->user()->currentAccessToken()->delete();

        $token = $student->createToken('mobile', [
            'grupa_id:' . $group->grupa_id,
            'cb_number:' . $group->cb_number,
        ])->plainTextToken;

        return response()->json([
            'token'    => $token,
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
