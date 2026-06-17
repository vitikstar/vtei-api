# VTEI Mobile App — План розробки

## Стек

- **React Native** (Expo) — кросплатформа iOS + Android
- **TypeScript**
- **Expo Router** — файлова навігація (tabs + stack)
- **TanStack Query** — кешування запитів до API
- **Zustand** — глобальний стейт (токен, профіль)
- **AsyncStorage** — зберігання токена між сесіями
- **NativeWind** або StyleSheet — стилізація

---

## Структура екранів

```
app/
├── (auth)/
│   ├── login.tsx           — логін
│   └── select-group.tsx    — вибір групи (якщо кілька)
│
└── (app)/                  — захищені екрани (після логіну)
    ├── _layout.tsx         — Tab Navigator (4 таби)
    │
    ├── (schedule)/
    │   ├── index.tsx       — розклад (тижневий вигляд)
    │   └── lesson/[id].tsx — деталі заняття
    │
    ├── (grades)/
    │   ├── index.tsx       — успішність по семестрах
    │   ├── modules.tsx     — модульні картки поточного року
    │   └── modules/[card_id].tsx — деталі картки
    │
    ├── (journal)/
    │   ├── index.tsx       — залікова книжка
    │   └── markbook.tsx    — екзамени/заліки по роках
    │
    └── (profile)/
        ├── index.tsx       — профіль студента
        └── settings.tsx    — налаштування (FCM, пароль)
```

---

## Таби (Bottom Navigation)

| Таб | Іконка | Екрани |
|---|---|---|
| Розклад | calendar | schedule/index, lesson/[id] |
| Успішність | chart-bar | grades/index, modules, modules/[id] |
| Журнал | book-open | journal/index (залікова книжка) |
| Профіль | user | profile/index, settings |

---

## Екрани — детальний опис

### Auth

#### `login.tsx`
- Поля: email/логін + пароль
- POST `/api/auth/login`
- Якщо `needs_cb_select: true` → перейти на `select-group`
- Зберегти токен в AsyncStorage + Zustand

#### `select-group.tsx`
- Список груп що прийшли з login response
- PUT `/api/auth/select-group` → зберегти токен

---

### Розклад

#### `schedule/index.tsx`
- Горизонтальний скролл по тижнях (← →)
- Кожен день — список карточок занять
- Карточка: час, предмет, викладач, аудиторія, тип (лекція/практика...)
- Фільтри: по типу заняття, викладачу (GET `/schedule/teachers`)
- GET `/api/schedule?period=week&date=YYYY-MM-DD`

#### `schedule/lesson/[id].tsx`
- Повна інформація про заняття
- Тема, домашнє завдання, нотатки
- Викладач (ім'я, email)
- Аудиторія + корпус
- GET `/api/schedule/lesson/{id}`

---

### Успішність

#### `grades/index.tsx`
- Список навчальних років (акордеон або вкладки)
- Всередині: семестри → предмети
- По кожному предмету: модуль 1 / модуль 2 / підсумок / ECTS
- GET `/api/grades`

#### `grades/modules.tsx`
- Поточний рік — картки по предметах
- Прогрес-бар набраних балів (sum/max)
- GET `/api/grades/modules`

#### `grades/modules/[card_id].tsx`
- Деталі модулів: список завдань з балами
- GET `/api/grades/modules/{card_id}`

---

### Журнал

#### `journal/index.tsx` (Залікова книжка)
- Список навчальних років (вкладки або акордеон)
- По кожному предмету: mod1 + mod2 + оцінка + ECTS + дата
- Фільтр: екзамен/залік
- GET `/api/grades/markbook`

---

### Профіль

#### `profile/index.tsx`
- Фото, ім'я, email, група, курс
- Статус (активний/не активний)
- Кнопки: Змінити пароль, Налаштування, Вийти
- GET `/api/profile`

#### `profile/settings.tsx`
- Toggle push-нотифікацій (FCM token)
- Telegram username (відображення)
- Зміна пароля (PUT `/api/profile/password`)
- GET/PUT `/api/settings`

---

## API endpoints — статус

### Auth
| Endpoint | Статус |
|---|---|
| POST `/auth/login` | ✅ |
| PUT `/auth/select-group` | ✅ |
| POST `/auth/logout` | ✅ |
| POST `/auth/recovery` | ✅ |
| POST `/auth/reset-password` | ✅ |

### Profile
| Endpoint | Статус |
|---|---|
| GET `/profile` | ✅ |
| PUT `/profile/password` | ✅ |
| POST `/profile/avatar` | ✅ |

### Schedule
| Endpoint | Статус |
|---|---|
| GET `/schedule` | ✅ |
| GET `/schedule/lesson/{id}` | ✅ |
| GET `/schedule/teachers` | ✅ |
| GET `/schedule/auditoriums` | ✅ |
| GET `/schedule/subjects` | ✅ |
| GET `/schedule/groups` | ✅ |
| GET `/schedule/lesson-types` | ✅ |

### Grades
| Endpoint | Статус |
|---|---|
| GET `/grades` | ✅ |
| GET `/grades/modules` | ✅ |
| GET `/grades/modules/{card_id}` | ✅ |
| GET `/grades/markbook` | ✅ |
| GET `/grades/attendance` | ❌ не реалізовано |

### Disciplines
| Endpoint | Статус |
|---|---|
| GET `/disciplines/electives` | ✅ |
| GET `/disciplines/electives/selected` | ✅ |
| POST `/disciplines/electives/save` | ✅ |
| DELETE `/disciplines/electives/{id}` | ✅ |
| GET `/disciplines/electives/pdf` | ❌ не реалізовано |
| GET `/disciplines/history` | ✅ |

### FAQ
| Endpoint | Статус |
|---|---|
| GET `/faq` | ✅ |
| GET `/faq/{group_id}/questions` | ✅ |
| POST `/faq/question` | ✅ |

### Voting
| Endpoint | Статус |
|---|---|
| GET `/voting` | ✅ |
| POST `/voting/vote` | ✅ |
| GET `/voting/results` | ✅ |

### Settings
| Endpoint | Статус |
|---|---|
| GET `/settings` | ✅ |
| PUT `/settings/notifications` | ✅ |

---

## Що потрібно додати на бекенді

1. **`GET /grades/attendance`** — відвідуваність (якщо є дані в БД)
2. **`GET /disciplines/electives/pdf`** — PDF заяви вибіркових дисциплін
3. **`GET /dashboard`** — вже є, але перевірити чи достатньо даних для головного екрану (можна додати таб "Головна" пізніше)

---

## Пріоритети розробки

### Фаза 1 — MVP (обов'язково)
1. Auth flow (login → select-group → home)
2. Розклад (тижневий вигляд + деталі заняття)
3. Успішність (поточний рік + залікова книжка)
4. Профіль (інфо + logout)

### Фаза 2
5. Налаштування (пароль, push)
6. Вибіркові дисципліни
7. FAQ

### Фаза 3
8. Голосування
9. Фільтри розкладу
10. Офлайн кеш (TanStack Query persistence)
