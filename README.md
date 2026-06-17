# VTEI Student API

REST API для мобільного додатку студентів ВТЕІ.
Laravel 12 · Sanctum · OpenAPI 3.0 (Swagger)

---

## Швидкий старт

```bash
git clone git@github.com:vitikstar/vtei-api.git
cd vtei-api

composer install
cp .env.example .env
php artisan key:generate
```

Налаштуй `.env`:

```env
DB_HOST=127.0.0.1
DB_DATABASE=vtei_api
DB_USERNAME=root
DB_PASSWORD=

DB_S_HOST=127.0.0.1
DB_S_DATABASE=base
DB_S_USERNAME=root
DB_S_PASSWORD=
```

```bash
php artisan migrate
php artisan serve
```

---

## Swagger документація

Після запуску сервера документація доступна за адресою:

```
http://localhost:8000/api/documentation
```

### Як користуватись

1. Відкрий `http://localhost:8000/api/documentation` у браузері
2. Перед першим запитом виконай **POST /api/auth/login** — введи логін і пароль
3. Скопіюй `token` з відповіді
4. Натисни кнопку **Authorize** (замок зверху праворуч)
5. Введи токен у полі: `Bearer <твій_токен>`
6. Тепер всі захищені ендпоінти доступні для тестування прямо в UI

### Регенерація документації

Якщо додав нові Swagger анотації — перегенеруй:

```bash
php artisan l5-swagger:generate
```

---

## Структура API

| Префікс | Опис | Авторизація |
|---|---|---|
| `POST /api/auth/login` | Вхід студента | Ні |
| `POST /api/auth/logout` | Вихід | Так |
| `GET /api/profile` | Профіль студента | Так |
| `GET /api/dashboard` | Дашборд | Так |
| `GET /api/schedule` | Розклад | Так |
| `GET /api/grades` | Оцінки | Так |
| `GET /api/disciplines/electives` | Вибіркові дисципліни | Так |
| `GET /api/faq` | FAQ | Ні |
| `GET /api/voting` | Голосування | Так |

Повний опис всіх ендпоінтів — у [FUNCTIONAL.MD](FUNCTIONAL.MD)

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
