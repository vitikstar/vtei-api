# VTEI Student API — Функціональний опис

Laravel 12 REST API для мобільного додатку студентів ВТЕІ.  
Swagger (L5-Swagger / OpenAPI 3.0) — документація за замовчуванням.

---

## Стек

- **Laravel 12**, PHP 8.2
- **Sanctum** — token-based авторизація (Bearer tokens)
- **MySQL** — дві бази: основна (`db`) і студентська/розклад (`db_s`)
- **L5-Swagger** — auto-generated OpenAPI 3.0 документація
- **Laravel Pint** — code style
- **Pest** — тести

---

## Бази даних

| Connection | Database | Використання |
|---|---|---|
| `mysql` (default) | `base` / основна | Користувачі, FAQ, анкети, голосування |
| `mysql_s` | `db_s` | Розклад, оцінки, модулі, групи, дисципліни |

---

## Автентифікація

### POST /api/auth/login
Вхід студента за CB-номером або email + пароль.  
Повертає Bearer token (Sanctum) + дані профілю.

**Body:**
```json
{
  "login": "string",   // cb_number або email
  "password": "string"
}
```

**Response 200:**
```json
{
  "token": "string",
  "student": {
    "id": 1,
    "name": "Іванов Іван Іванович",
    "email": "student@vtei.edu.ua",
    "photo_url": "string|null",
    "role_code": 15,
    "status": 2
  }
}
```

**Логіка:**
- Пошук по `users_student` (email або cb_number через `asu_grupa_student`)
- Перевірка пароля (bcrypt)
- Якщо студент у кількох групах — повертає список груп для вибору (`needs_cb_select: true`)

---

### POST /api/auth/select-group
Якщо студент у кількох групах — вибрати активний CB-номер.

**Body:**
```json
{
  "cb_number": "string"
}
```

---

### POST /api/auth/logout
Відкликає поточний token. Вимагає авторизації.

---

### POST /api/auth/recovery
Надіслати email для відновлення паролю.

**Body:**
```json
{
  "email": "string"
}
```

---

### POST /api/auth/reset-password
Встановити новий пароль за access_token з email.

**Body:**
```json
{
  "access_token": "string",
  "password": "string",
  "password_confirmation": "string"
}
```

---

## Профіль студента

> Всі ендпоінти вимагають Bearer token.

### GET /api/profile
Повна інформація про поточного студента.

**Response 200:**
```json
{
  "id": 1,
  "name": "Іванов Іван Іванович",
  "email": "student@vtei.edu.ua",
  "photo_url": "string|null",
  "gender": 1,
  "home_address": "string|null",
  "group": {
    "cb_number": "string",
    "group_name": "string",
    "course": 2,
    "faculty": "string",
    "specialty": "string"
  }
}
```

---

### PUT /api/profile/password
Змінити пароль.

**Body:**
```json
{
  "current_password": "string",
  "password": "string",
  "password_confirmation": "string"
}
```

---

### POST /api/profile/avatar
Завантажити аватар. `multipart/form-data`, поле `avatar` (jpg/png, max 2MB).

---

## Дашборд

### GET /api/dashboard
Загальна статистика для головного екрану студента.

**Response 200:**
```json
{
  "active_lessons_today": 3,
  "disciplines_count": 12,
  "average_grade": 74.5,
  "upcoming_exams": [
    {
      "subject": "Математика",
      "date": "2025-01-15",
      "type": "exam"
    }
  ],
  "current_semester": 2,
  "current_year": "2024-2025"
}
```

---

## Розклад

### GET /api/schedule
Розклад занять для студента.

**Query params:**
| Param | Type | Default | Опис |
|---|---|---|---|
| `week_start` | date (Y-m-d) | поточний тиждень | Початок тижня |
| `group_id` | int | група студента | Фільтр по групі |

**Response 200:**
```json
{
  "week_start": "2025-01-13",
  "week_end": "2025-01-19",
  "days": [
    {
      "date": "2025-01-13",
      "weekday": "Понеділок",
      "lessons": [
        {
          "id": 1,
          "period": 1,
          "time_start": "08:00",
          "time_end": "09:35",
          "subject": "Вища математика",
          "teacher": "Петренко О.В.",
          "auditorium": "214",
          "lesson_type": "Лекція",
          "group_name": "КН-21-1",
          "theme": "string|null"
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
  "id": 1,
  "subject": "Вища математика",
  "teacher": {
    "name": "Петренко О.В.",
    "email": "string|null"
  },
  "auditorium": "214",
  "lesson_type": "Лекція",
  "theme": "string|null",
  "date": "2025-01-13",
  "time_start": "08:00",
  "time_end": "09:35",
  "groups": ["КН-21-1", "КН-21-2"]
}
```

---

### GET /api/schedule/calendar
Події для calendar-view (FullCalendar/мобільний календар).

**Query params:** `start` (date), `end` (date), `group_id` (int, optional)

**Response 200:** масив подій у форматі:
```json
[
  {
    "id": 1,
    "title": "Вища математика (Лекція)",
    "start": "2025-01-13T08:00:00",
    "end": "2025-01-13T09:35:00",
    "color": "#3788d8"
  }
]
```

---

## Оцінки та успішність

### GET /api/grades
Список дисциплін з оцінками поточного семестру.

**Query params:** `semester` (1|2), `year` (string, напр. `2024-2025`)

**Response 200:**
```json
{
  "semester": 2,
  "year": "2024-2025",
  "disciplines": [
    {
      "subject": "Вища математика",
      "teacher": "Петренко О.В.",
      "mark1": 45,
      "mark2": 30,
      "exam_score": 40,
      "total": 115,
      "ects": "B",
      "national_grade": "добре"
    }
  ],
  "average": 74.5
}
```

---

### GET /api/grades/modules
Модульні оцінки по картках.

**Response 200:**
```json
[
  {
    "card_id": 123,
    "subject": "Вища математика",
    "semester": 2,
    "modules": [
      {
        "module_number": 1,
        "score": 45,
        "max_score": 50,
        "date": "2025-01-10"
      }
    ]
  }
]
```

---

### GET /api/grades/modules/{card_id}
Детальна розбивка оцінок по конкретній картці модуля.

**Response 200:**
```json
{
  "card_id": 123,
  "subject": "Вища математика",
  "modules": [
    {
      "module_number": 1,
      "score": 45,
      "max_score": 50,
      "tasks": [
        {
          "name": "Контрольна робота",
          "score": 20,
          "max_score": 25,
          "date": "2025-01-05"
        }
      ]
    }
  ],
  "total": 115,
  "ects": "B"
}
```

---

### GET /api/grades/markbook
Залікова книжка — всі семестри.

**Response 200:**
```json
[
  {
    "semester": 1,
    "year": "2023-2024",
    "disciplines": [
      {
        "subject": "Алгебра",
        "total": 90,
        "ects": "A",
        "national_grade": "відмінно",
        "exam_date": "2024-01-20"
      }
    ]
  }
]
```

---

### GET /api/grades/attendance
Пропуски занять студента.

**Query params:** `semester` (1|2), `year` (string)

**Response 200:**
```json
{
  "total_lessons": 120,
  "attended": 100,
  "missed": 20,
  "attendance_percent": 83.3,
  "by_subject": [
    {
      "subject": "Вища математика",
      "total": 30,
      "missed": 5
    }
  ]
}
```

---

## Вибіркові дисципліни

### GET /api/disciplines/electives
Список доступних вибіркових дисциплін для студента.

**Query params:** `semester` (1|2)

**Response 200:**
```json
{
  "semester": 2,
  "stage": 1,
  "selection_open": true,
  "groups": [
    {
      "group_id": 5,
      "name": "Група вибіркових 1",
      "disciplines": [
        {
          "id": 10,
          "name": "Веб-розробка",
          "credits": 3,
          "students_enrolled": 12,
          "min_required": 10,
          "max_capacity": 25,
          "status": "green",
          "selected": false
        }
      ]
    }
  ]
}
```

**Статус дисципліни:**
- `red` — менше мінімуму для формування групи
- `yellow` — наближається до мінімуму
- `green` — набрано достатньо студентів

---

### GET /api/disciplines/electives/selected
Вже вибрані студентом дисципліни.

---

### POST /api/disciplines/electives/save
Зберегти вибір дисциплін.

**Body:**
```json
{
  "semester": 2,
  "discipline_ids": [10, 15, 22]
}
```

**Валідація:**
- Кількість дисциплін відповідає нормативу
- Розподіл по семестрах S1/S2 коректний
- Вибір ще відкритий (selection_open: true)

---

### DELETE /api/disciplines/electives/{id}
Видалити вибір дисципліни (якщо вибір ще відкритий).

---

### GET /api/disciplines/electives/pdf
Згенерувати PDF-декларацію вибраних дисциплін. Повертає файл.

---

### GET /api/disciplines/history
Історія дисциплін по семестрах.

---

## FAQ

### GET /api/faq
Список груп FAQ.

**Response 200:**
```json
[
  {
    "id": 1,
    "name": "Загальні питання",
    "questions_count": 15
  }
]
```

---

### GET /api/faq/{group_id}/questions
Питання у групі.

**Query params:** `search` (string, optional)

**Response 200:**
```json
{
  "group": {"id": 1, "name": "Загальні питання"},
  "questions": [
    {
      "id": 10,
      "question": "Як замовити довідку?",
      "answer": "<p>...</p>"
    }
  ]
}
```

---

### POST /api/faq/question
Задати нове питання (відправляє email адміну).

**Body:**
```json
{
  "group_id": 1,
  "question": "Текст питання",
  "email": "student@vtei.edu.ua"
}
```

---

## Голосування

### GET /api/voting
Поточне голосування (якщо активне).

**Response 200:**
```json
{
  "is_active": true,
  "title": "Вибори старости групи",
  "already_voted": false,
  "candidates": [
    {
      "id": 1,
      "name": "Петренко І.О.",
      "photo_url": "string|null",
      "votes": 15
    }
  ]
}
```

---

### POST /api/voting/vote
Проголосувати.

**Body:**
```json
{
  "candidate_id": 1
}
```

---

### GET /api/voting/results
Результати голосування.

---

## Налаштування

### GET /api/settings
Поточні налаштування студента.

---

### PUT /api/settings/notifications
Налаштування push-сповіщень (майбутнє).

**Body:**
```json
{
  "schedule_notifications": true,
  "grade_notifications": true
}
```

---

## Статичні сторінки

### GET /api/pages/privacy — Політика конфіденційності
### GET /api/pages/terms — Умови використання
### GET /api/pages/about — Про додаток

---

## Коди помилок

| HTTP | Code | Опис |
|---|---|---|
| 401 | `UNAUTHENTICATED` | Відсутній або невалідний token |
| 403 | `FORBIDDEN` | Немає доступу до ресурсу |
| 404 | `NOT_FOUND` | Ресурс не знайдено |
| 422 | `VALIDATION_ERROR` | Помилки валідації |
| 500 | `SERVER_ERROR` | Помилка сервера |

**Формат помилки:**
```json
{
  "message": "Текст помилки",
  "code": "ERROR_CODE",
  "errors": {
    "field": ["Validation message"]
  }
}
```

---

## Swagger / OpenAPI

- URL документації: `GET /api/documentation`
- JSON schema: `GET /api/documentation.json`
- Пакет: `darkaonline/l5-swagger`
- Всі захищені ендпоінти мають `securityScheme: BearerAuth`

---

## Структура проекту Laravel

```
app/
  Http/
    Controllers/
      Api/
        AuthController.php
        ProfileController.php
        DashboardController.php
        ScheduleController.php
        GradeController.php
        DisciplineController.php
        FaqController.php
        VotingController.php
        SettingsController.php
        PageController.php
    Middleware/
      EnsureStudentActive.php    // перевірка status=2
    Resources/
      StudentResource.php
      LessonResource.php
      GradeResource.php
      DisciplineResource.php
      FaqResource.php
  Models/
    Student.php          // users_student (db_s)
    Lesson.php           // rozklad_nv_timetable_classes
    Grade.php            // mod_list
    ModCard.php          // mod_cards
    Subject.php          // subjects
    StudentGroup.php     // asu_grupa_student
    FaqQuestion.php      // faq_qa
    FaqGroup.php         // faq_groups
    Discipline.php       // asu_predmet_student
    VoteCandidate.php    // rss_candidate_db
  Services/
    AuthService.php
    GradeService.php
    ScheduleService.php
    DisciplineService.php
config/
  database.php           // два connections: mysql + mysql_s
routes/
  api.php
```

---

## Пріоритет реалізації

1. **Auth** (login, logout, token)
2. **Profile** (GET, change password)
3. **Schedule** (тижневий розклад)
4. **Grades** (оцінки, модулі, залікова)
5. **Dashboard**
6. **FAQ**
7. **Elective disciplines**
8. **Voting**
9. **Settings / static pages**
10. **PDF export, Avatar upload**
