# CLAUDE.md — VTEI Student API

## Що це за проект

Laravel 12 REST API для мобільного додатку студентів ВТЕІ (Вінницький торговельно-економічний інститут).
Мобільний додаток підключається до цього API щоб показувати розклад, оцінки, профіль, дашборд тощо.

**Репозиторій:** https://github.com/vitikstar/vtei-api  
**Локальний шлях:** `/Users/viktorbaranov/Sites/vtei-api`

---

## Стек

- **Laravel 12**, PHP 8.2
- **Laravel Sanctum** — Bearer token авторизація
- **MySQL 8** в Docker — два з'єднання (дві бази)
- **L5-Swagger** (darkaonline/l5-swagger ^11, swagger-php v6) — OpenAPI 3.0 документація
- **PHP 8 Attributes** для Swagger — НЕ docblock анотації (`#[OA\Get(...)]`, не `/** @OA\Get */`)

---

## Бази даних

| Laravel connection | База | Що зберігає |
|---|---|---|
| `mysql` (default) | `ems_edu` | Студенти, групи, розклад, викладачі, FAQ, голосування |
| `mysql_s` | `main` | Оцінки (mod_list, mod_cards), екзамени (dec_exam, dec_exam_header), предмети (subjects) |

**Docker MySQL:** порт `3308` (не 3306, бо зайнятий іншим)  
**phpMyAdmin:** http://localhost:8080 (root / root)

### Запуск Docker

```bash
cd /Users/viktorbaranov/Sites/vtei-api
docker compose up -d
```

### Ключові таблиці

**ems_edu:**
- `users_student` — студенти (id, name, email, pwd, photo_url, gender, status, fcm_token)
- `asu_grupa_student` — прив'язка студента до групи (student_id, cb_number, grupa_id, archive)
- `asu_grupa` — групи (id, name, course, ...)
- `rozklad_nv_timetable_classes` — заняття (id, day, para, date, year, predmet_id, teacher_id, aud_id, type_hours, theme_name)
- `rozklad_nv_timetable_classes_group_st` — прив'язка занять до груп (timetable_id, grupa_id, student_id)
- `rozklad_nv_call_schedule` — розклад дзвінків (number, start, end)
- `rozklad_nv_auditory_data` — аудиторії (id, title, case_number/building)
- `asu_predmet` — предмети/дисципліни (id, name)
- `users` — викладачі (id, t_name, short_t_name, email)
- `faq_qa` — питання FAQ (id, question, answer, group_id)
- `faq_groups` — групи FAQ (id, name)
- `rss_voted_head` — голосування
- `rss_candidate` — кандидати

**main:**
- `mod_list` — модульні оцінки (id, mod_card_id, stud_id, login, mark1, mark2, total, ects)
- `mod_cards` — модульні картки (id, subject, st_group, sem, teacher, teach_year, is_exam, credits)
- `dec_exam` — результати екзаменів (id, head_id, stud_id, date, score, mark)
- `dec_exam_header` — заголовки екзаменів (id, subj_id, group_id, year, sem_num, date, is_exam)
- `subjects` — предмети (id, subject) — поле називається `subject`, не `name`!

---

## Моделі та їх connections

| Модель | Файл | Connection | Таблиця |
|---|---|---|---|
| Student | app/Models/Student.php | `mysql` | `users_student` |
| StudentGroup | app/Models/StudentGroup.php | `mysql` | `asu_grupa_student` |
| Lesson | app/Models/Lesson.php | `mysql` | `rozklad_nv_timetable_classes` |
| Grade | app/Models/Grade.php | `mysql_s` | `mod_list` |
| ModCard | app/Models/ModCard.php | `mysql_s` | `mod_cards` |
| FaqQuestion | app/Models/FaqQuestion.php | `mysql` | `faq_qa` |
| FaqGroup | app/Models/FaqGroup.php | `mysql` | `faq_groups` |
| Discipline | app/Models/Discipline.php | `mysql` | `asu_predmet` |
| VoteCandidate | app/Models/VoteCandidate.php | `mysql` | `rss_candidate` |

**Student** використовує Sanctum (`HasApiTokens`), пароль в полі `pwd` (не `password`).  
`getAuthPassword()` повертає `$this->pwd`.  
Хеш паролю — bcrypt (сумісний з `Hash::check()`).

---

## Авторизація

Sanctum Bearer token. Всі захищені маршрути мають middleware `auth:sanctum` + `student.active`.

`student.active` — `EnsureStudentActive` middleware, перевіряє `$student->status === 2`.

**Статуси студента:** 1=не активний, 2=активний, 3=видалений

---

## Навчальний рік

```php
private function currentYear(): string
{
    $year = now()->year;
    $month = now()->month;
    if ($month >= 9) {
        return $year . '/' . ($year + 1);
    }
    return ($year - 1) . '/' . $year;
}
```

---

## Swagger

- **Пакет:** `darkaonline/l5-swagger ^11` + `zircote/swagger-php v6`
- **Синтаксис:** PHP 8 Attributes — `#[OA\Get(...)]` на методах контролерів
- **НЕ використовувати** docblock `/** @OA\Get */` — swagger-php v6 їх не читає
- **Базова конфігурація** (`@OA\Info`, `@OA\SecurityScheme`, `@OA\Server`) — у файлі `app/OpenApiSpec.php`
- **Генерація:** `php artisan l5-swagger:generate`
- **UI:** http://localhost:8000/api/documentation

### Шаблон методу зі Swagger

```php
#[OA\Get(
    path: '/endpoint',
    summary: 'Опис',
    security: [['BearerAuth' => []]],
    tags: ['TagName'],
    parameters: [
        new OA\Parameter(name: 'param', in: 'query', schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Успіх'),
        new OA\Response(response: 401, description: 'Не авторизований'),
    ]
)]
public function method(Request $request): JsonResponse
```

---

## Маршрути (routes/api.php)

### Публічні
- `POST /api/auth/login`
- `POST /api/auth/select-group`
- `POST /api/auth/recovery`
- `POST /api/auth/reset-password`
- `GET /api/faq`
- `GET /api/faq/{group_id}/questions`
- `POST /api/faq/question`
- `GET /api/pages/privacy|terms|about`

### Захищені (auth:sanctum + student.active)
- `POST /api/auth/logout`
- `GET /api/profile` | `PUT /api/profile/password` | `POST /api/profile/avatar`
- `GET /api/dashboard`
- `GET /api/schedule` | `GET /api/schedule/lesson/{id}` | `GET /api/schedule/calendar`
- `GET /api/grades` | `GET /api/grades/modules` | `GET /api/grades/modules/{card_id}` | `GET /api/grades/markbook` | `GET /api/grades/attendance`
- `GET /api/disciplines/electives` | `GET /api/disciplines/electives/selected` | `POST /api/disciplines/electives/save` | `DELETE /api/disciplines/electives/{id}` | `GET /api/disciplines/electives/pdf` | `GET /api/disciplines/history`
- `GET /api/voting` | `POST /api/voting/vote` | `GET /api/voting/results`
- `GET /api/settings` | `PUT /api/settings/notifications`

---

## Реалізовані ендпоінти

### ✅ Auth (AuthController)
- `POST /api/auth/login` — логін, повертає token + student або `needs_cb_select: true` зі списком груп
- `POST /api/auth/select-group` — вибрати групу при кількох
- `POST /api/auth/logout`
- `POST /api/auth/recovery`
- `POST /api/auth/reset-password`

### ✅ Dashboard (DashboardController)
- `GET /api/dashboard` — студент, група, заняття сьогодні, кількість дисциплін, середній бал, найближчі екзамени

### ✅ Schedule (ScheduleController)
- `GET /api/schedule?week_start=YYYY-MM-DD&group_id=N` — тижневий розклад по днях
- `GET /api/schedule/lesson/{id}` — деталі заняття (тема, домашнє, нотатки, групи)
- `GET /api/schedule/calendar?start=...&end=...` — події для календаря з кольорами

### ❌ Не реалізовано (черга)
- Profile
- Grades (mod_list + dec_exam)
- Disciplines (electives)
- FAQ
- Voting
- Settings

---

## Типи занять

```php
'lecture_hours'    => 'Лекція',
'lecturing_hours'  => 'Лекція',
'practical_hours'  => 'Практика',
'lab_hours'        => 'Лабораторна',
'seminar_hours'    => 'Семінар',
'consultation'     => 'Консультація',
'individual_hours' => 'Індивідуальна',
```

---

## Правила розробки

1. **Swagger** — завжди додавати `#[OA\...]` атрибути до кожного публічного методу контролера
2. **Після змін** — запускати `php artisan l5-swagger:generate` перед комітом
3. **Два connections** — уважно стежити: `mysql` = ems_edu, `mysql_s` = main
4. **Поле пароля** у студента — `pwd`, не `password`
5. **Поле предмета** у таблиці `subjects` — `subject`, не `name`
6. **Викладачі** — таблиця `users` в ems_edu, поля `t_name` (повне), `short_t_name` (скорочене)
7. **Тестовий логін:** lilibasalyuk@gmail.com / password (пароль скинутий локально в Docker)
8. **Git:** після кожного реалізованого ендпоінту — коміт + push на github.com/vitikstar/vtei-api

---

## Корисні команди

```bash
# Запустити сервер
php artisan serve --port=8000

# Запустити Docker
docker compose up -d

# Згенерувати Swagger
php artisan l5-swagger:generate

# Перевірити маршрути
php artisan route:list --path=api

# Очистити кеш
php artisan config:clear && php artisan cache:clear

# Тест логіну
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"lilibasalyuk@gmail.com","password":"password"}'
```
