<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\StudentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Info(
 *     title="VTEI Student API",
 *     version="1.0.0",
 *     description="REST API для мобільного додатку студентів ВТЕІ"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="BearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * @OA\Server(url="/api", description="API Server")
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Auth"},
     *     summary="Вхід студента",
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"login","password"},
     *             @OA\Property(property="login", type="string", example="student@vtei.edu.ua"),
     *             @OA\Property(property="password", type="string", example="secret")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Успішний вхід",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="student", type="object"),
     *             @OA\Property(property="needs_cb_select", type="boolean"),
     *             @OA\Property(property="groups", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=422, description="Помилка валідації або неправильні дані")
     * )
     */
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

        $groups = StudentGroup::where('student_id', $student->id)->get();

        if ($groups->count() > 1) {
            return response()->json([
                'needs_cb_select' => true,
                'student_id' => $student->id,
                'groups' => $groups->map(fn($g) => [
                    'cb_number' => $g->cb_number,
                    'group_name' => $g->grupa_id,
                ]),
            ]);
        }

        $token = $student->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'needs_cb_select' => false,
            'student' => new StudentResource($student),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/select-group",
     *     tags={"Auth"},
     *     summary="Вибір активної групи (для студентів у кількох групах)",
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"student_id","cb_number"},
     *             @OA\Property(property="student_id", type="integer"),
     *             @OA\Property(property="cb_number", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Токен та профіль студента")
     * )
     */
    public function selectGroup(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|integer',
            'cb_number' => 'required|string',
        ]);

        $student = Student::findOrFail($request->student_id);

        $groupExists = StudentGroup::where('student_id', $student->id)
            ->where('cb_number', $request->cb_number)
            ->exists();

        if (!$groupExists) {
            return response()->json(['message' => 'Групу не знайдено'], 404);
        }

        $token = $student->createToken('mobile', ['cb_number:' . $request->cb_number])->plainTextToken;

        return response()->json([
            'token' => $token,
            'student' => new StudentResource($student),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Auth"},
     *     summary="Вихід з системи",
     *     security={{"BearerAuth":{}}},
     *     @OA\Response(response=200, description="Успішний вихід")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вихід виконано']);
    }

    /**
     * @OA\Post(
     *     path="/auth/recovery",
     *     tags={"Auth"},
     *     summary="Запит на відновлення паролю",
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Email надіслано")
     * )
     */
    public function recovery(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // TODO: надіслати email з токеном відновлення

        return response()->json(['message' => 'Якщо email існує — лист надіслано']);
    }

    /**
     * @OA\Post(
     *     path="/auth/reset-password",
     *     tags={"Auth"},
     *     summary="Встановити новий пароль за токеном",
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"access_token","password","password_confirmation"},
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="password", type="string", minLength=6),
     *             @OA\Property(property="password_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Пароль змінено")
     * )
     */
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
