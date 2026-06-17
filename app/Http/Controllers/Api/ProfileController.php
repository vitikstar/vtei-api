<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: '/profile',
        summary: 'Повна інформація про поточного студента',
        security: [['BearerAuth' => []]],
        tags: ['Profile'],
        responses: [
            new OA\Response(response: 200, description: 'Дані студента'),
            new OA\Response(response: 401, description: 'Не авторизований'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $student = $request->user();

        $active = $this->activeGroup($request);

        $group = $active
            ? DB::connection('mysql')
                ->table('asu_grupa_student as gs')
                ->join('asu_grupa as g', 'g.id', '=', 'gs.grupa_id')
                ->where('gs.student_id', $student->id)
                ->where('gs.cb_number', $active->cb_number)
                ->select('gs.cb_number', 'gs.grupa_id', 'g.name as group_name', 'g.course')
                ->first()
            : null;

        return response()->json([
            'id'              => $student->id,
            'name'            => $student->name,
            'email'           => $student->email,
            'photo_url'       => $student->photo_url,
            'gender'          => $student->gender,
            'date_birth'      => $student->date_birth,
            'home_address'    => $student->home_address1,
            'tg_username'     => $student->tg_username,
            'doc_education'   => $student->doc_education,
            'document_type_id'=> $student->document_type_id,
            'document_series' => $student->document_series,
            'document_number' => $student->document_number,
            'end_register'    => $student->end_register,
            'status'          => $student->status,
            'created_at'      => $student->created_at,
            'last_visit'      => $student->last_visit,
            'group'           => $group ? [
                'cb_number'  => $group->cb_number,
                'grupa_id'   => $group->grupa_id,
                'group_name' => $group->group_name,
                'course'     => $group->course,
            ] : null,
        ]);
    }

    #[OA\Put(
        path: '/profile/password',
        summary: 'Зміна паролю',
        security: [['BearerAuth' => []]],
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6),
                    new OA\Property(property: 'password_confirmation', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Пароль змінено'),
            new OA\Response(response: 422, description: 'Невірний поточний пароль'),
        ]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:6|confirmed',
        ]);

        $student = $request->user();

        if (!Hash::check($request->current_password, $student->pwd)) {
            return response()->json([
                'message' => 'Невірний поточний пароль.',
                'errors'  => ['current_password' => ['Невірний поточний пароль.']],
            ], 422);
        }

        $student->update(['pwd' => Hash::make($request->password)]);

        return response()->json(['message' => 'Пароль успішно змінено']);
    }

    #[OA\Post(
        path: '/profile/avatar',
        summary: 'Завантажити аватар',
        security: [['BearerAuth' => []]],
        tags: ['Profile'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Аватар оновлено'),
            new OA\Response(response: 422, description: 'Невалідний файл'),
        ]
    )]
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $student = $request->user();

        $path = $request->file('avatar')->store('avatars', 'public');
        $filename = basename($path);

        $student->update(['photo_url' => $filename]);

        return response()->json([
            'message'   => 'Аватар оновлено',
            'photo_url' => $filename,
        ]);
    }
}
