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
    "completed_calls_count": 5,
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

**Валидация:**
- Форматы: JPEG, PNG, GIF, WebP
- Максимальный размер файла: 2 MB
- Минимальные размеры изображения: 50×50 пикселей
- Максимальные размеры изображения: 2000×2000 пикселей

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

При загрузке нового аватара старый файл автоматически удаляется.

**Ошибки:**
- `400` — `{"error": "File upload required or upload failed"}`
- `400` — `{"error": "Only JPEG, PNG, GIF, WebP allowed"}`
- `400` — `{"error": "File too large (max 2MB)"}`
- `400` — `{"error": "Invalid image file"}`
- `400` — `{"error": "Image too small (min 50x50 pixels)"}`
- `400` — `{"error": "Image too large (max 2000x2000 pixels)"}`
- `500` — `{"error": "Failed to create upload directory"}`
- `500` — `{"error": "Upload directory is not writable"}`
- `500` — `{"error": "Failed to save file", "details": "..."}`

---

### DELETE /api/users/me/avatar

Удалить аватарку профиля.

**Auth:** да

**Тело запроса:** пустое

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
  "message": "Avatar deleted"
}
```

**Ошибки:**
- `500` — `{"error": "Failed to delete avatar"}`

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
    "completed_calls_count": 5,
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

### GET /api/users/{id}/completed-calls-count

Получить количество завершённых видеозвонков пользователя.

**Auth:** нет

**Параметры пути:** `id` — ID пользователя

**Ответ 200:**
```json
{
  "completed_calls_count": 5
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

## 9. Навыки пользователей (user_skills)

### GET /api/user-skills

Получить все навыки всех пользователей (содержимое таблицы user_skills).

**Auth:** да

**Ответ 200:**
```json
{
  "user_skills": [
    {
      "user_id": 1,
      "skill_id": 1,
      "type": "teach",
      "proficiency_level": 5,
      "description": null,
      "created_at": "2025-02-15T12:00:00Z",
      "username": "ivan",
      "full_name": "Иван Петров",
      "avatar_url": null,
      "skill_name": "Английский",
      "category_name": "Языки"
    }
  ]
}
```

**Ошибки:**
- `401` — `{"error": "Unauthorized"}`

---

### GET /api/user-skills/{userId}

Получить навыки конкретного пользователя. Результаты сортируются по релевантности для текущего авторизованного пользователя:

**Приоритеты сортировки:**
1. **(+10 баллов)** Целевой пользователь преподаёт то, что текущий хочет изучить (комплементарный обмен)
2. **(+5 баллов)** Целевой пользователь хочет изучить то, что текущий преподаёт (комплементарный обмен)
3. **(+2 балла)** Навык относится к той же категории, что и навыки текущего пользователя

**Auth:** да

**Параметры пути:** `userId` — ID пользователя

**Ответ 200:**
```json
{
  "user": {
    "id": 2,
    "username": "marie",
    "full_name": "Мари",
    "avatar_url": null
  },
  "user_skills": [
    {
      "user_id": 2,
      "skill_id": 4,
      "type": "teach",
      "proficiency_level": 5,
      "description": null,
      "created_at": "2025-02-15T12:00:00Z",
      "username": "marie",
      "full_name": "Мари",
      "avatar_url": null,
      "skill_name": "Французский",
      "category_name": "Языки"
    }
  ]
}
```

**Ошибки:**
- `401` — `{"error": "Unauthorized"}`
- `404` — `{"error": "User not found"}`

---

## 10. Push-уведомления (Push Tokens)

### GET /api/push-tokens

Получить список моих зарегистрированных push-токенов.

**Auth:** да

**Ответ 200:**
```json
{
  "tokens": [
    {
      "id": 1,
      "user_id": 1,
      "push_token": "fcm_token_string...",
      "platform": "android",
      "device_name": "Samsung Galaxy S21",
      "device_id": "device123",
      "created_at": "2025-02-15T12:00:00Z",
      "updated_at": "2025-02-15T12:00:00Z"
    }
  ]
}
```

---

### POST /api/push-tokens

Зарегистрировать или обновить push-токен для получения уведомлений.

**Auth:** да

**Тело запроса (JSON):**
| Поле | Тип | Обязательно | Описание |
|------|-----|-------------|----------|
| push_token | string | да | FCM токен устройства |
| platform | string | нет | Платформа: `android`, `ios`, `web` (по умолч. `android`) |
| device_name | string | нет | Название устройства |
| device_id | string | нет | Уникальный ID устройства |

**Пример запроса:**
```json
{
  "push_token": "fcm_token_string...",
  "platform": "android",
  "device_name": "Samsung Galaxy S21"
}
```

**Ответ 201:**
```json
{
  "message": "Token registered",
  "id": 1
}
```

**Ошибки:**
- `400` — `{"error": "push_token required"}`
- `400` — `{"error": "Invalid platform. Must be android, ios, or web"}`

---

### DELETE /api/push-tokens/{id}

Удалить push-токен (например, при выходе из аккаунта на устройстве).

**Auth:** да

**Параметры пути:** `id` — ID токена

**Ответ 200:**
```json
{
  "message": "Token deleted"
}
```

**Ошибки:**
- `404` — `{"error": "Token not found"}`

---

## Автоматические push-уведомления

Сервер автоматически отправляет push-уведомления через **FCM HTTP v1 API** (Firebase Cloud Messaging) в следующих случаях:

| Событие | Когда отправляется | Заголовок уведомления |
|---------|-------------------|----------------------|
| Новое сообщение | При `POST /api/messages` | "Новое сообщение" |
| Входящий звонок | При `POST /api/video-calls` | "Входящий звонок" |
| Новый отзыв | При `POST /api/reviews` | "Новый отзыв" |

### Настройка

Для аутентификации используется файл сервисного аккаунта Firebase: `src/bartery-1-firebase-adminsdk-fbsvc-20493bcfca.json`.
Сервер автоматически получает OAuth2 access token через JWT и отправляет уведомления через `https://fcm.googleapis.com/v1/projects/bartery-1/messages:send`.

### Формат данных уведомления

Каждое push-уведомление содержит поле `data` с дополнительной информацией:

**Сообщение:**
```json
{
  "type": "message",
  "sender_name": "ivan"
}
```

**Звонок:**
```json
{
  "type": "call",
  "caller_name": "ivan",
  "call_id": "1"
}
```

**Отзыв:**
```json
{
  "type": "review",
  "reviewer_name": "ivan",
  "rating": "5"
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
| DELETE | /api/users/me/avatar | да | Удалить аватарку |
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
| GET | /api/user-skills | да | Все навыки всех пользователей |
| GET | /api/user-skills/{userId} | да | Навыки конкретного пользователя (с сортировкой по релевантности) |
| GET | /api/push-tokens | да | Мои push-токены |
| POST | /api/push-tokens | да | Зарегистрировать push-токен |
| DELETE | /api/push-tokens/{id} | да | Удалить push-токен |
