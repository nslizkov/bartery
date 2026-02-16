# Skills Exchange — Спецификация API

**Базовый URL:** пока нет, но скоро будет (`http://localhost:8080/api` для разработки)

**Формат:** JSON (запрос и ответ)  
**Кодировка:** UTF-8

**Даты:** ISO 8601 (`2025-02-15T12:00:00Z`), удобно парсить в Kotlin/Retrofit

---

## Авторизация

Для эндпоинтов, помеченных как **Auth: да**, в заголовок каждого запроса нужно передавать:

```
Authorization: Bearer <token>
```

Токен возвращается при успешном входе (`POST /api/auth/login`) и регистрации (`POST /api/auth/register`).

---

## Общие ошибки

При ошибке сервер возвращает JSON:

```json
{
  "error": "текст ошибки"
}
```

Типичные HTTP-коды:
- `400` — неверные данные (валидация)
- `401` — не авторизован (нет/неверный токен)
- `404` — ресурс не найден
- `409` — конфликт (например, email уже занят)

---

# Эндпоинты

---

## 1. Авторизация

### POST /api/auth/register

Регистрация нового пользователя.

**Auth:** нет  

**Тело запроса (JSON):**
| Поле       | Тип    | Обязательно | Описание                    |
|-----------|--------|-------------|-----------------------------|
| username  | string | да          | Логин (уникальный)          |
| email     | string | да          | Email (уникальный)          |
| password  | string | да          | Пароль, мин. 6 символов     |
| full_name | string | нет         | Имя пользователя            |

**Пример запроса:**
```json
{
  "username": "ivan",
  "email": "ivan@example.com",
  "password": "secret123",
  "full_name": "Иван Петров"
}
```

**Ответ 201:**
```json
{
  "user": {
    "id": 1,
    "username": "ivan",
    "email": "ivan@example.com",
    "full_name": "Иван Петров",
    "bio": null,
    "avatar_url": null,
    "role": "user",
    "points": 0
  },
  "token": "64-символьная строка"
}
```

**Ошибки:**
- `400` — `{"error": "username, email and password required"}` или `{"error": "Password must be at least 6 characters"}`
- `409` — `{"error": "Username or email already exists"}`

---

### POST /api/auth/login

Вход в аккаунт.

**Auth:** нет  

**Тело запроса (JSON):**
| Поле     | Тип    | Обязательно | Описание |
|----------|--------|-------------|----------|
| email    | string | да          | Email    |
| password | string | да          | Пароль   |

**Пример запроса:**
```json
{
  "email": "ivan@example.com",
  "password": "secret123"
}
```

**Ответ 200:**
```json
{
  "user": {
    "id": 1,
    "username": "ivan",
    "email": "ivan@example.com",
    "full_name": "Иван Петров",
    "bio": null,
    "avatar_url": null,
    "role": "user",
    "points": 0
  },
  "token": "64-символьная строка"
}
```

**Ошибки:**
- `400` — `{"error": "email and password required"}`
- `401` — `{"error": "Invalid credentials"}`

---

### POST /api/auth/logout

Выход (инвалидация токена).

**Auth:** да (токен опционален)  

**Тело запроса:** пустое или `{}`  

**Ответ 200:**
```json
{
  "message": "Logged out"
}
```

---

## 2. Категории

### GET /api/categories

Список категорий навыков.

**Auth:** нет  

**Ответ 200:**
```json
{
  "categories": [
    {
      "id": 1,
      "name": "Языки",
      "description": "Иностранные языки и лингвистика"
    }
  ]
}
```

---

## 3. Навыки

### GET /api/skills

Список навыков.

**Auth:** нет  

**Query-параметры:**
| Параметр    | Тип | Описание                          |
|-------------|-----|-----------------------------------|
| category_id | int | Фильтр по ID категории (опц.)     |

**Пример:** `GET /api/skills?category_id=2`  

**Ответ 200:**
```json
{
  "skills": [
    {
      "id": 1,
      "name": "Python",
      "description": "Язык программирования Python",
      "category_id": 2,
      "category_name": "Программирование"
    }
  ]
}
```

---

### POST /api/skills

Создать навык.

**Auth:** да  

**Тело запроса (JSON):**
| Поле        | Тип    | Обязательно | Описание           |
|-------------|--------|-------------|--------------------|
| name        | string | да          | Название навыка    |
| description | string | нет         | Описание           |
| category_id | int    | нет         | ID категории       |

**Пример запроса:**
```json
{
  "name": "React",
  "description": "Библиотека для UI",
  "category_id": 2
}
```

**Ответ 201:**
```json
{
  "skill": {
    "id": 8,
    "name": "React",
    "description": "Библиотека для UI",
    "category_id": 2,
    "category_name": "Программирование"
  }
}
```

**Ошибки:**
- `400` — `{"error": "name required"}`
- `409` — `{"error": "Skill with this name already exists"}`

---

## 4. Пользователи

### GET /api/users

Список всех активных пользователей (с пагинацией).

**Auth:** да  

**Query-параметры:**
| Параметр | Тип | По умолч. | Описание |
|----------|-----|-----------|----------|
| limit    | int | 50        | Записей на страницу (макс. 100) |
| offset   | int | 0         | Смещение |

**Пример:** `GET /api/users?limit=20&offset=0`  

**Ответ 200:**
```json
{
  "users": [
    {
      "id": 1,
      "username": "ivan",
      "full_name": "Иван Петров",
      "bio": null,
      "avatar_url": null,
      "points": 0,
      "created_at": "2025-02-15T12:00:00Z"
    }
  ],
  "total": 150,
  "limit": 20,
  "offset": 0
}
```

---

### GET /api/users/me

Профиль текущего пользователя (с навыками).

**Auth:** да  

**Ответ 200:**
```json
{
  "user": {
    "id": 1,
    "username": "ivan",
    "email": "ivan@example.com",
    "full_name": "Иван Петров",
    "bio": null,
    "avatar_url": null,
    "role": "user",
    "points": 0,
    "created_at": "2025-02-15T12:00:00Z",
    "teach_count": 2,
    "learn_count": 1,
    "skills": [
      {
        "skill_id": 1,
        "type": "teach",
        "proficiency_level": 3,
        "description": null,
        "skill_name": "Python",
        "category_name": "Программирование"
      }
    ]
  }
}
```

---

### PUT /api/users/me

Обновить профиль текущего пользователя.

**Auth:** да  

**Тело запроса (JSON):** только поля, которые меняются.

| Поле       | Тип    | Описание              |
|------------|--------|-----------------------|
| full_name  | string | Имя                   |
| bio        | string | Биография             |
| avatar_url | string | URL аватара           |

**Пример запроса:**
```json
{
  "full_name": "Иван Петров",
  "bio": "Разработчик на Python"
}
```

**Ответ 200:**
```json
{
  "user": {
    "id": 1,
    "username": "ivan",
    "email": "ivan@example.com",
    "full_name": "Иван Петров",
    "bio": "Разработчик на Python",
    "avatar_url": null,
    "role": "user",
    "points": 0
  }
}
```

**Ошибки:**
- `400` — `{"error": "Invalid body"}` или `{"error": "No fields to update"}`

---

### POST /api/users/me/avatar

Загрузить аватарку профиля.

**Auth:** да  

**Тело запроса:** `multipart/form-data`  

| Поле    | Тип  | Обязательно | Описание                        |
|---------|------|-------------|---------------------------------|
| avatar  | file | да          | Изображение (JPEG, PNG, GIF, WebP), макс. 2 МБ |

**Пример запроса (curl):**
```bash
curl -X POST https://your-domain.com/api/users/me/avatar \
  -H "Authorization: Bearer <token>" \
  -F "avatar=@photo.jpg"
```

**Ответ 200:**
```json
{
  "user": {
    "id": 1,
    "username": "ivan",
    "email": "ivan@example.com",
    "full_name": "Иван Петров",
    "bio": null,
    "avatar_url": "/uploads/avatars/1_1708012345.jpg",
    "role": "user",
    "points": 0
  },
  "avatar_url": "/uploads/avatars/1_1708012345.jpg"
}
```

`avatar_url` — путь к файлу на сервере. Файлы раздаются по тому же домену, полный URL: `{базовый_домен}{avatar_url}` (например, `https://your-domain.com/uploads/avatars/1_1708012345.jpg`). Если `avatar_url` пустой — аватарка не загружена.

**Ошибки:**
- `400` — `{"error": "File upload required or upload failed"}`  
- `400` — `{"error": "Only JPEG, PNG, GIF, WebP allowed"}`  
- `400` — `{"error": "File too large (max 2MB)"}`  
- `500` — `{"error": "Failed to save file"}`  

---

### GET /api/users/me/skills

Список навыков текущего пользователя.

**Auth:** да  

**Ответ 200:**
```json
{
  "skills": [
    {
      "skill_id": 1,
      "type": "teach",
      "proficiency_level": 3,
      "description": null,
      "skill_name": "Python",
      "category_name": "Программирование"
    }
  ]
}
```

---

### POST /api/users/me/skills

Добавить навык себе (или обновить, если уже есть).

**Auth:** да  

**Тело запроса (JSON):**
| Поле             | Тип    | Обязательно | Описание                           |
|------------------|--------|-------------|------------------------------------|
| skill_id         | int    | да          | ID навыка                          |
| type             | string | да          | `"teach"` (учу) или `"learn"` (хочу научиться) |
| proficiency_level| int    | нет         | Уровень 1–5 (по умолч. 1)          |
| description      | string | нет         | Комментарий                        |

**Пример запроса:**
```json
{
  "skill_id": 4,
  "type": "learn",
  "proficiency_level": 1,
  "description": "Начальный уровень"
}
```

**Ответ 201:**
```json
{
  "skill": {
    "skill_id": 4,
    "type": "learn",
    "proficiency_level": 1,
    "description": "Начальный уровень",
    "skill_name": "Французский"
  }
}
```

**Ошибки:**
- `400` — `{"error": "skill_id and type (teach|learn) required"}` или `{"error": "Invalid skill_id"}`

---

### DELETE /api/users/me/skills/{skillId}

Удалить навык у текущего пользователя.

**Auth:** да  

**Параметры пути:** `skillId` — ID навыка  

**Ответ 200:**
```json
{
  "message": "Skill removed"
}
```

---

### GET /api/users/search

Поиск партнёров по обмену.

**Auth:** нет  

**Query-параметры:**
| Параметр | Тип | Обязательно | Описание                                      |
|----------|-----|-------------|-----------------------------------------------|
| teach    | int | да          | ID навыка, которому вы учите                  |
| learn    | int | да          | ID навыка, которому хотите научиться          |

Ищутся пользователи, которые учат `learn` и хотят учить `teach`.

**Пример:** `GET /api/users/search?teach=1&learn=4`  

**Ответ 200:**
```json
{
  "users": [
    {
      "id": 2,
      "username": "marie",
      "full_name": "Мари",
      "bio": null,
      "avatar_url": null,
      "points": 0,
      "skills": [
        {"name": "Французский", "type": "teach"},
        {"name": "Python", "type": "learn"}
      ]
    }
  ]
}
```

**Ошибки:**
- `400` — `{"error": "teach and learn (skill ids) query params required"}`

---

### GET /api/users/{id}

Публичный профиль пользователя.

**Auth:** нет  

**Параметры пути:** `id` — ID пользователя  

**Ответ 200:**
```json
{
  "user": {
    "id": 2,
    "username": "marie",
    "full_name": "Мари",
    "bio": null,
    "avatar_url": null,
    "points": 0,
    "created_at": "2025-02-15T12:00:00Z",
    "skills": [
      {
        "skill_id": 4,
        "type": "teach",
        "proficiency_level": 5,
        "description": null,
        "skill_name": "Французский",
        "category_name": "Языки"
      }
    ]
  }
}
```

**Ошибки:**
- `404` — `{"error": "User not found"}`

---

## 5. Сообщения

### GET /api/messages

Список диалогов текущего пользователя (с пагинацией).

**Auth:** да  

**Query-параметры:**
| Параметр | Тип | По умолч. | Описание |
|----------|-----|-----------|----------|
| limit    | int | 50        | Записей на страницу (макс. 50) |
| offset   | int | 0         | Смещение |

**Пример:** `GET /api/messages?limit=20&offset=0`  

**Ответ 200:**
```json
{
  "conversations": [
    {
      "id": 2,
      "username": "marie",
      "full_name": "Мари",
      "avatar_url": null,
      "last_message": "Привет!",
      "last_at": "2025-02-15T14:30:00Z",
      "unread": "2"
    }
  ],
  "total": 5,
  "limit": 20,
  "offset": 0
}
```

---

### GET /api/messages/{userId}

Сообщения в диалоге с пользователем.

**Auth:** да  

**Параметры пути:** `userId` — ID собеседника  

**Ответ 200:**
```json
{
  "messages": [
    {
      "id": 1,
      "sender_id": 1,
      "receiver_id": 2,
      "content": "Привет!",
      "is_read": 1,
      "created_at": "2025-02-15T14:00:00Z"
    }
  ]
}
```

*(Сообщения, адресованные текущему пользователю, помечаются как прочитанные.)*

---

### POST /api/messages

Отправить сообщение.

**Auth:** да  

**Тело запроса (JSON):**
| Поле        | Тип    | Обязательно | Описание           |
|-------------|--------|-------------|--------------------|
| receiver_id | int    | да          | ID получателя      |
| content     | string | да          | Текст (макс. 5000 символов) |

**Пример запроса:**
```json
{
  "receiver_id": 2,
  "content": "Привет! Готова позаниматься?"
}
```

**Ответ 201:**
```json
{
  "message": {
    "id": 1,
    "sender_id": 1,
    "receiver_id": 2,
    "content": "Привет! Готова позаниматься?",
    "is_read": 0,
    "created_at": "2025-02-15 14:00:00"
  }
}
```

**Ошибки:**
- `400` — `{"error": "receiver_id and content required"}` или `{"error": "Message too long"}` или `{"error": "Cannot send message to yourself"}`
- `404` — `{"error": "User not found"}`

---

## 6. Отзывы

### GET /api/reviews/{userId}

Отзывы о пользователе.

**Auth:** нет  

**Параметры пути:** `userId` — ID пользователя  

**Ответ 200:**
```json
{
  "reviews": [
    {
      "id": 1,
      "reviewer_id": 1,
      "rating": 5,
      "comment": "Отличное занятие!",
      "created_at": "2025-02-15T15:00:00Z",
      "reviewer_username": "ivan",
      "reviewer_name": "Иван",
      "reviewer_avatar": null
    }
  ],
  "average_rating": 4.5,
  "total": 3
}
```

---

### POST /api/reviews

Оставить или обновить отзыв о пользователе (один отзыв на пару reviewer–reviewed).

**Auth:** да  

**Тело запроса (JSON):**
| Поле        | Тип    | Обязательно | Описание                    |
|-------------|--------|-------------|-----------------------------|
| reviewed_id | int    | да          | ID пользователя, о котором отзыв |
| rating      | int    | да          | Оценка 1–5                  |
| comment     | string | нет         | Текст отзыва                |

**Пример запроса:**
```json
{
  "reviewed_id": 2,
  "rating": 5,
  "comment": "Очень помогла с французским!"
}
```

**Ответ 201:**
```json
{
  "review": {
    "id": 1,
    "reviewer_id": 1,
    "reviewed_id": 2,
    "rating": 5,
    "comment": "Очень помогла с французским!",
    "created_at": "2025-02-15 15:00:00"
  }
}
```

**Ошибки:**
- `400` — `{"error": "reviewed_id and rating (1-5) required"}` или `{"error": "rating must be 1-5"}`
- `400` — `{"error": "Cannot review yourself"}`

---

## 7. Видеозвонки

### GET /api/video-calls

История звонков текущего пользователя.

**Auth:** да  

**Ответ 200:**
```json
{
  "calls": [
    {
      "id": 1,
      "caller_id": 1,
      "callee_id": 2,
      "started_at": "2025-02-15T16:00:00Z",
      "ended_at": "2025-02-15T16:30:00Z",
      "duration": 1800,
      "status": "completed",
      "other_username": "marie",
      "other_name": "Мари",
      "other_avatar": null
    }
  ]
}
```

---

### POST /api/video-calls

Создать звонок (статус `pending`).

**Auth:** да  

**Тело запроса (JSON):**
| Поле      | Тип | Обязательно | Описание  |
|-----------|-----|-------------|-----------|
| callee_id | int | да          | ID того, кому звоним |

**Пример запроса:**
```json
{
  "callee_id": 2
}
```

**Ответ 201:**
```json
{
  "call": {
    "id": 1,
    "caller_id": 1,
    "callee_id": 2,
    "started_at": "2025-02-15T16:00:00Z",
    "ended_at": null,
    "duration": null,
    "status": "pending"
  }
}
```

**Ошибки:**
- `400` — `{"error": "callee_id required"}` или `{"error": "Cannot call yourself"}`

---

### PATCH /api/video-calls/{id}

Обновить статус звонка.

**Auth:** да  

**Параметры пути:** `id` — ID звонка  

**Тело запроса (JSON):**
| Поле   | Тип    | Обязательно | Описание                                               |
|--------|--------|-------------|--------------------------------------------------------|
| status | string | да          | `"active"`, `"completed"`, `"cancelled"`               |

При `"completed"` заполняются `ended_at` и `duration` (секунды).

**Пример запроса:**
```json
{
  "status": "completed"
}
```

**Ответ 200:**
```json
{
  "call": {
    "id": 1,
    "caller_id": 1,
    "callee_id": 2,
    "started_at": "2025-02-15T16:00:00Z",
    "ended_at": "2025-02-15T16:30:00Z",
    "duration": 1800,
    "status": "completed"
  }
}
```

**Ошибки:**
- `400` — `{"error": "status required"}` или `{"error": "Invalid status"}`
- `404` — `{"error": "Call not found"}`

---

## 8. Бейджи

### GET /api/badges

Список всех бейджей.

**Auth:** нет  

**Ответ 200:**
```json
{
  "badges": [
    {
      "id": 1,
      "name": "Первый обмен",
      "description": "Провёл первое занятие",
      "image_url": null,
      "criteria": null
    }
  ]
}
```

---

### GET /api/badges/user/{userId}

Бейджи пользователя.

**Auth:** нет  

**Параметры пути:** `userId` — ID пользователя  

**Ответ 200:**
```json
{
  "badges": [
    {
      "id": 1,
      "name": "Первый обмен",
      "description": "Провёл первое занятие",
      "image_url": null,
      "awarded_at": "2025-02-15T12:00:00Z"
    }
  ]
}
```

---

## Сводная таблица

| Метод | Путь | Auth | Описание |
|-------|------|------|----------|
| POST | /api/auth/register | нет | Регистрация |
| POST | /api/auth/login | нет | Вход |
| POST | /api/auth/logout | да | Выход |
| GET | /api/categories | нет | Список категорий |
| GET | /api/skills | нет | Список навыков |
| POST | /api/skills | да | Создать навык |
| GET | /api/users | да | Список пользователей |
| GET | /api/users/me | да | Мой профиль |
| PUT | /api/users/me | да | Обновить профиль |
| POST | /api/users/me/avatar | да | Загрузить аватарку |
| GET | /api/users/me/skills | да | Мои навыки |
| POST | /api/users/me/skills | да | Добавить навык |
| DELETE | /api/users/me/skills/{skillId} | да | Удалить навык |
| GET | /api/users/search?teach=X&learn=Y | нет | Поиск партнёров |
| GET | /api/users/{id} | нет | Профиль пользователя |
| GET | /api/messages | да | Список диалогов |
| GET | /api/messages/{userId} | да | Сообщения с пользователем |
| POST | /api/messages | да | Отправить сообщение |
| GET | /api/reviews/{userId} | нет | Отзывы о пользователе |
| POST | /api/reviews | да | Оставить отзыв |
| GET | /api/video-calls | да | История звонков |
| POST | /api/video-calls | да | Создать звонок |
| PATCH | /api/video-calls/{id} | да | Обновить статус звонка |
| GET | /api/badges | нет | Список бейджей |
| GET | /api/badges/user/{userId} | нет | Бейджи пользователя |
