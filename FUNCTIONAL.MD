# VTEI Student API — Функціональний опис

Laravel 12 REST API для мобільного додатку студентів ВТЕІ.  
Swagger (L5-Swagger / OpenAPI 3.0) — документація: `GET /api/documentation`

---

## Стек

- **Laravel 12**, PHP 8.2
- **Sanctum** — Bearer token авторизація
- **MySQL 8** (Docker, порт 3308) — дві бази
- **L5-Swagger** (`darkaonline/l5-swagger ^11`, `swagger-php v6`) — OpenAPI 3.0
- **PHP 8 Attributes** для Swagger (`#[OA\Get(...)]`)

---

## Бази даних

| Connection | Database | Використання |
|---|---|---|
| `mysql` (default) | `ems_edu` | Студенти, групи, розклад, FAQ, голосування, вибіркові |
| `mysql_s` | `main` | Оцінки, модулі, екзамени, предмети |

---

## Автентифікація

### POST /api/auth/login
Вхід за email або CB-номером + пароль.

**Body:**
```json
{ "login": "string", "password": "string" }
```

**Response 200 — одна група:**
```json
{
  "token": "string",
  "student": { "id": 1, "name": "...", "email": "...", "photo_url": null, "status": 2 }
}
```

**Response 200 — кілька груп (needs_cb_select):**
```json
{
  "needs_cb_select": true,
  "student": { "id": 1, "name": "..." },
  "groups": [{ "cb_number": "860821", "group_name": "МН-21-1", "course": 4 }]
}
```

---

### PUT /api/auth/select-group
Вибрати активну групу (якщо студент у кількох).

**Body:** `{ "cb_number": "string" }`  
**Response:** `{ "token": "string", "student": {...} }`

---

### POST /api/auth/logout
Відкликає поточний token. Вимагає авторизації.

---

### POST /api/auth/recovery
Надіслати email для відновлення паролю. **Body:** `{ "email": "string" }`

---

### POST /api/auth/reset-password
Встановити новий пароль.

**Body:**
```json
{ "access_token": "string", "password": "string", "password_confirmation": "string" }
```

---

## Профіль студента

> Всі ендпоінти вимагають Bearer token + middleware `student.active` (status=2).

### GET /api/profile
Повна інформація про поточного студента.

**Response 200:**
```json
{
  "id": 26044,
  "name": "Басалюк Лілія Олегівна",
  "email": "lilibasalyuk@gmail.com",
  "photo_url": null,
  "gender": 0,
  "group": {
    "cb_number": "860821",
    "group_name": "МН-21-1",
    "course": 4,
    "faculty": "Факультет...",
    "specialty": "..."
  }
}
```

---

### PUT /api/profile/password
**Body:** `{ "current_password": "...", "password": "...", "password_confirmation": "..." }`

---

### POST /api/profile/avatar
`multipart/form-data`, поле `avatar` (jpg/png, max 2MB).

---

## Дашборд

### GET /api/dashboard
Загальна статистика для головного екрану.

**Response 200:**
```json
{
  "student": { "id": 1, "name": "...", "group": "МН-21-1", "course": 4 },
  "today_lessons": [...],
  "disciplines_count": 9,
  "average_grade": 74.5,
  "upcoming_exams": [{ "subject": "...", "date": "2025-06-10" }],
  "current_semester": 2,
  "current_year": "2024-2025"
}
```

---

## Розклад

### GET /api/schedule
Розклад занять. За замовчуванням — поточний навчальний рік.

**Query params:**
| Param | Type | Опис |
|---|---|---|
| `period` | `year\|month\|week\|day` | Швидкий вибір (default: `year`) |
| `date` | `Y-m-d` | Опорна дата для period (default: сьогодні) |
| `date_from` | `Y-m-d` | Початок довільного діапазону |
| `date_to` | `Y-m-d` | Кінець довільного діапазону |
| `teacher_id` | int | Фільтр по викладачу |
| `auditorium_id` | int | Фільтр по аудиторії |
| `subject_id` | int | Фільтр по дисципліні |
| `lesson_type` | string | Фільтр по типу заняття |

**Response 200:**
```json
{
  "period": "week",
  "date_from": "2025-06-16",
  "date_to": "2025-06-22",
  "grupa_id": 17971,
  "days": [
    {
      "date": "2025-06-16",
      "weekday": "Понеділок",
      "lessons": [
        {
          "id": 1, "para": 1, "time_start": "08:00", "time_end": "09:35",
          "subject": "Вища математика", "subject_id": 10,
          "teacher": "Петренко О.В.", "teacher_id": 5,
          "auditorium": "214", "auditorium_id": 20, "building": 1,
          "lesson_type": "Лекція", "lesson_type_key": "lecturing_hours",
          "theme": null, "is_remote": false
        }
      ]
    }
  ]
}
```

---

### GET /api/schedule/lesson/{id}
Детальна інформація про заняття.

**Response 200:**
```json
{
  "id": 1, "subject": "Вища математика", "subject_id": 10,
  "lesson_type": "Лекція", "lesson_type_key": "lecturing_hours",
  "theme": "Тема 1", "homework": null, "notes": null, "is_remote": false,
  "day": "Понеділок", "date": "2025-06-16", "para": 1,
  "time_start": "08:00", "time_end": "09:35",
  "teacher": { "id": 5, "name": "Петренко О.В.", "short": "Петренко О.В.", "email": "..." },
  "auditorium": "214", "auditorium_id": 20, "building": 1,
  "groups": ["МН-21-1", "МН-21-2"]
}
```

---

## Довідники розкладу

Використовуються для заповнення фільтрів в `/schedule`.

### GET /api/schedule/teachers
Викладачі з розкладу групи, згруповані по кафедрах.

```json
[{ "kafedra_id": 5, "kafedra_name": "Кафедра...", "teachers": [{ "id": 5, "name": "...", "short_name": "...", "email": "..." }] }]
```

---

### GET /api/schedule/auditoriums
Аудиторії з розкладу групи, згруповані по корпусах.

```json
[{ "building_id": 1, "building_name": "Корпус 1", "auditoriums": [{ "id": 20, "title": "214", "floor": 2 }] }]
```

---

### GET /api/schedule/subjects
Дисципліни з розкладу групи (плоский список).

```json
[{ "id": 10, "name": "Вища математика" }]
```

---

### GET /api/schedule/groups
Всі групи інституту, згруповані по факультетах.

```json
[{ "faculty_id": 3, "faculty_name": "ФЕМ", "groups": [{ "id": 17971, "name": "МН-21-1", "course": 4 }] }]
```

---

### GET /api/schedule/lesson-types
Всі типи занять з ключами для фільтрації.

```json
[{ "key": "lecturing_hours", "label": "Лекція" }, { "key": "practical_hours", "label": "Практика" }]
```

---

## Оцінки та успішність

### GET /api/grades
Вся успішність студента, згрупована по навчальних роках і семестрах. Включає модульні оцінки та результати екзаменів/заліків.

**Response 200:**
```json
{
  "data": [
    {
      "year": "2021-2022",
      "semesters": [
        {
          "semester": 1,
          "subjects": [
            {
              "card_id": 1290, "subject_id": 192,
              "subject_name": "Іноземна мова", "credits": 6, "is_exam": false,
              "mod1": { "sum": "25", "mark": "2" },
              "mod2": { "sum": "26", "mark": "2" },
              "total": "51", "ects": "FX",
              "exam": { "score": 60, "mark": 3, "mark_label": "Задовільно", "extra_points": 0 }
            }
          ]
        }
      ]
    }
  ]
}
```

---

### GET /api/grades/modules
Модульні картки поточного навчального року.

**Response 200:**
```json
{
  "year": "2025-2026",
  "data": [
    {
      "card_id": 15065, "subject_id": 946, "subject_name": "Контролінг",
      "semester": 7, "credits": 0, "is_exam": true,
      "mod1": { "sum": "0", "max": 50, "mark": "0" },
      "mod2": { "sum": "0", "max": 50, "mark": "0" },
      "total": "0", "ects": ""
    }
  ]
}
```

---

### GET /api/grades/modules/{card_id}
Деталі модульної картки: розбивка по окремих завданнях.

**Response 200:**
```json
{
  "card_id": 1290, "subject_name": "Іноземна мова",
  "semester": 1, "teach_year": "2021-2022", "is_exam": false, "credits": 6,
  "mod1": {
    "sum": "25", "mark": "2", "max": 50,
    "tasks": [{ "name": "Unit 1. Business...", "score": 7 }]
  },
  "mod2": { "sum": "26", "mark": "2", "max": 50, "tasks": [...] },
  "total": "51", "ects": "FX"
}
```

---

### GET /api/grades/markbook
Залікова книжка — всі екзамени та заліки, згруповані по роках і семестрах.

**Response 200:**
```json
{
  "data": [
    {
      "year": "2021-2022",
      "semesters": [
        {
          "semester": 1,
          "subjects": [
            {
              "subject_id": 192, "subject_name": "Іноземна мова",
              "credits": 6, "is_exam": false,
              "mod1": 25, "mod2": 26, "extra_points": 0,
              "score": 60, "mark": 3, "mark_label": "Задовільно",
              "exam_date": "2021-12-06"
            }
          ]
        }
      ]
    }
  ]
}
```

---

### GET /api/grades/attendance
> ❌ Не реалізовано — відсутні дані відвідуваності в БД.

---

## Вибіркові дисципліни

### GET /api/disciplines/electives
Доступні вибіркові дисципліни для освітньої програми і курсу студента. Включає інформацію про поточну/наступну хвилю вибору.

**Response 200:**
```json
{
  "year": "2025-2026",
  "wave": {
    "status": "active",
    "stage": 1, "name": "Перша хвиля",
    "time_start": "2026-02-23 06:00:00",
    "time_end": "2026-02-24 21:47:00"
  },
  "data": [
    {
      "id": 1, "name": "Digital-технології в бізнесі", "name_en": "...",
      "has_exam": false, "kafedra_id": 104, "kafedra_name": "Кафедра...",
      "min_students": 15, "max_students": 25,
      "selected_count": 183, "is_selected": false,
      "syllabuses": ["https://cabinet.vtei.edu.ua/uploads/elective/abc.pdf"]
    }
  ]
}
```

**`wave.status`:** `"active"` | `"upcoming"` | `null` (немає хвиль)

---

### GET /api/disciplines/electives/selected
Дисципліни, які студент обрав у поточному навчальному році.

**Response 200:**
```json
{
  "year": "2025-2026",
  "data": [
    {
      "id": 25616, "predmet_id": 552, "name": "...", "name_en": "...",
      "has_exam": false, "semester": 1, "stage": 1, "confirmed": true,
      "kafedra_id": 5, "kafedra_name": "...", "selected_at": "2022-02-03 15:26:35"
    }
  ]
}
```

---

### POST /api/disciplines/electives/save
Зберегти вибір дисциплін. Доступно тільки під час активної хвилі. Перезаписує вибір для вказаного семестру.

**Body:** `{ "predmet_ids": [1, 2, 3], "sem": 1 }`  
**Response 400** якщо хвиля не активна.

---

### DELETE /api/disciplines/electives/{id}
Видалити один запис з вибору (id з `nv_predmet_student_stage`).

---

### GET /api/disciplines/electives/pdf
> ❌ Не реалізовано.

---

### GET /api/disciplines/history
Всі дисципліни студента по навчальних роках.

**Response 200:**
```json
{
  "data": [
    {
      "year": "2021-2022", "course": 1,
      "disciplines": [{ "predmet_id": 47, "name": "Безпека життя", "name_en": "...", "has_exam": false, "kafedra_id": null, "kafedra_name": null }]
    }
  ]
}
```

---

## FAQ

### GET /api/faq
Список груп FAQ з кількістю питань. Публічний ендпоінт.

**Response 200:**
```json
{ "data": [{ "id": 2, "name": "Авторизація", "questions_count": 38 }] }
```

---

### GET /api/faq/{group_id}/questions
Питання і відповіді групи. HTML-теги очищені.

**Response 200:**
```json
{
  "group": { "id": 2, "name": "Авторизація" },
  "data": [{ "id": 24, "question": "Чому не авторизує?", "answer": null, "popular": false }]
}
```

---

### POST /api/faq/question
Задати нове питання. Публічний ендпоінт.

**Body:** `{ "question": "string (5-1000 chars)", "group_id": 2 }`  
**Response 201:** `{ "message": "Питання додано", "id": 123 }`

---

## Голосування

### GET /api/voting
Кандидати студентського самоврядування і статус голосування поточного студента.

**Response 200:**
```json
{
  "data": [
    { "id": 4, "name": "Іванов І.І.", "program": "Передвиборча програма...", "photo_url": "https://...", "student_id": 172082042 }
  ],
  "has_voted": false,
  "voted_for": null
}
```

---

### POST /api/voting/vote
Проголосувати. Повторне голосування повертає 409.

**Body:** `{ "candidate_student_id": 172082042 }`

---

### GET /api/voting/results
Результати з кількістю голосів і відсотками.

**Response 200:**
```json
{
  "total_votes": 336,
  "data": [{ "id": 4, "name": "...", "photo_url": "...", "votes": 138, "percentage": 41.1 }]
}
```

---

## Налаштування

### GET /api/settings
Поточні налаштування студента.

**Response 200:**
```json
{
  "notifications": { "fcm_enabled": false, "fcm_token": null },
  "telegram": { "username": null }
}
```

---

### PUT /api/settings/notifications
Оновити FCM токен для push-нотифікацій. Передати `null` щоб відключити.

**Body:** `{ "fcm_token": "string|null" }`

---

## Статичні сторінки

| Ендпоінт | Опис |
|---|---|
| `GET /api/pages/privacy` | Політика конфіденційності |
| `GET /api/pages/terms` | Умови використання |
| `GET /api/pages/about` | Про додаток |

---

## Коди помилок

| HTTP | Опис |
|---|---|
| 401 | Відсутній або невалідний token |
| 403 | Немає доступу (студент не активний) |
| 404 | Ресурс не знайдено |
| 409 | Конфлікт (вже проголосував, тощо) |
| 422 | Помилки валідації |
| 400 | Бізнес-логіка (хвиля закрита, тощо) |
| 500 | Помилка сервера |

**Формат помилки:**
```json
{ "message": "Текст помилки", "errors": { "field": ["Validation message"] } }
```

---

## Структура проекту

```
app/Http/Controllers/Api/
  AuthController.php
  ProfileController.php
  DashboardController.php
  ScheduleController.php      — розклад (index, lesson)
  ReferenceController.php     — довідники (teachers, auditoriums, subjects, groups, lesson-types)
  GradeController.php         — оцінки (index, modules, moduleDetail, markbook)
  DisciplineController.php    — дисципліни (electives, selected, save, destroy, history)
  FaqController.php           — FAQ (index, questions, store)
  VotingController.php        — голосування (index, vote, results)
  SettingsController.php      — налаштування (index, notifications)
  PageController.php          — статичні сторінки

app/Models/
  Student.php                 — users_student (mysql), HasApiTokens
  StudentGroup.php            — asu_grupa_student (mysql)
  Lesson.php                  — rozklad_nv_timetable_classes (mysql)
  Grade.php                   — mod_list (mysql_s)
  ModCard.php                 — mod_cards (mysql_s)
  FaqQuestion.php             — faq_qa (mysql)
  FaqGroup.php                — faq_groups (mysql)
  Discipline.php              — asu_predmet (mysql)
  VoteCandidate.php           — rss_candidate (mysql)

app/Http/Middleware/
  EnsureStudentActive.php     — перевірка status=2

app/OpenApiSpec.php           — @OA\Info, @OA\SecurityScheme, @OA\Server
```

---

## Статус реалізації

| Модуль | Статус |
|---|---|
| Auth (login, logout, recovery, reset, select-group) | ✅ |
| Profile (show, password, avatar) | ✅ |
| Dashboard | ✅ |
| Schedule (index, lesson) | ✅ |
| Schedule References (teachers, auditoriums, subjects, groups, lesson-types) | ✅ |
| Grades (index, modules, moduleDetail, markbook) | ✅ |
| Grades attendance | ❌ |
| Disciplines (electives, selected, save, destroy, history) | ✅ |
| Disciplines PDF | ❌ |
| FAQ (index, questions, store) | ✅ |
| Voting (index, vote, results) | ✅ |
| Settings (index, notifications FCM) | ✅ |
| Static pages (privacy, terms, about) | ✅ |
