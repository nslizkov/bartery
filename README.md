# Skills Exchange — Backend API

Платформа для обмена навыками. Бэкенд: PHP, MySQL, Docker.

## Структура проекта

```
server/
├── docker-compose.yml    # Оркестрация: proxy, app, web, mysql
├── Dockerfile            # Образ API (PHP + nginx)
├── web/Dockerfile        # Образ веб-модуля (PHP + nginx)
├── docker/
│   ├── proxy.conf        # Роутинг: / → web, /api → app
│   ├── nginx-app.conf    # API-контейнер
│   ├── nginx-web.conf    # Web-контейнер
│   └── supervisord*.conf
├── public/index.php      # API (роутер)
├── src/                  # Конфиг и обработчики API
├── web/                  # Веб-модуль (отдельный контейнер)
│   ├── *.html, css/, js/api.js
│   └── admin/            # Админка БД (PHP, подключение к MySQL)
└── README.md
```

## Запуск локально

### Требования
- Docker и Docker Compose

### Шаги

1. **Клонируй/скопируй проект и перейди в папку**
   ```bash
   cd server
   ```

2. **Создай `.env` (опционально)**
   ```bash
   cp .env.example .env
   ```
   Можно оставить значения по умолчанию.

3. **Запусти контейнеры**
   ```bash
   docker compose up -d
   ```

4. **Проверь работу**
   - Веб-версия: http://localhost:8080 (логин, регистрация, пользователи, сообщения, мой профиль)
   - Админка (БД): http://localhost:8080/admin/
   - API: http://localhost:8080/api/categories или `curl http://localhost:8080/api/categories`

5. **Остановка**
   ```bash
   docker compose down
   ```

## Развёртывание на удалённом сервере

### Вариант 1: Docker Compose

1. **Скопируй проект на сервер**
   ```bash
   scp -r server/ user@your-server:/home/user/
   ```

2. **Подключись по SSH**
   ```bash
   ssh user@your-server
   cd /home/user/server
   ```

3. **Создай `.env` с надёжными данными**
   ```bash
   cp .env.example .env
   nano .env
   ```
   Задай:
   - `DB_PASS` — сложный пароль для БД
   - `MYSQL_ROOT_PASSWORD` — пароль root MySQL

4. **Запусти**
   ```bash
   docker compose up -d
   ```

5. **Настрой домен и HTTPS (nginx как reverse proxy)**
   - Установи nginx на хост.
   - Создай конфиг:

   ```nginx
   server {
       listen 80;
       server_name api.yourdomain.com;

       location / {
           proxy_pass http://127.0.0.1:8080;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

   - Установи certbot и получи SSL: `certbot --nginx -d api.yourdomain.com`

### Вариант 2: Без Docker

1. Установи PHP 8.2+, MySQL 8+, nginx или Apache.
2. Создай БД и пользователя, выполни `db/init.sql`.
3. Скопируй `public/` и `src/` на сервер.
4. Настрой nginx/Apache, чтобы корень сайта указывал на `public/`.
5. В переменных окружения или в `src/config.php` задай параметры подключения к БД.

---

## API Endpoints

### Авторизация
| Метод | Путь | Описание |
|-------|------|----------|
| POST | /api/auth/register | Регистрация |
| POST | /api/auth/login | Вход |
| POST | /api/auth/logout | Выход |

### Пользователи
| Метод | Путь | Auth | Описание |
|-------|------|------|----------|
| GET | /api/users | ✓ | Список всех пользователей (для веб-версии) |
| GET | /api/users/me | ✓ | Профиль текущего пользователя |
| PUT | /api/users/me | ✓ | Обновить профиль |
| GET | /api/users/me/skills | ✓ | Мои навыки |
| POST | /api/users/me/skills | ✓ | Добавить навык |
| DELETE | /api/users/me/skills/{id} | ✓ | Удалить навык |
| GET | /api/users/search?teach=X&learn=Y | - | Поиск партнёров по обмену |
| GET | /api/users/{id} | - | Публичный профиль |

### Категории и навыки
| Метод | Путь | Auth | Описание |
|-------|------|------|----------|
| GET | /api/categories | - | Список категорий |
| GET | /api/skills | - | Список навыков (?category_id=X) |
| POST | /api/skills | ✓ | Создать навык |

### Сообщения
| Метод | Путь | Auth | Описание |
|-------|------|------|----------|
| GET | /api/messages | ✓ | Список диалогов |
| GET | /api/messages/{userId} | ✓ | Сообщения с пользователем |
| POST | /api/messages | ✓ | Отправить сообщение |

### Отзывы
| Метод | Путь | Auth | Описание |
|-------|------|------|----------|
| GET | /api/reviews/{userId} | - | Отзывы о пользователе |
| POST | /api/reviews | ✓ | Оставить отзыв |

### Видеозвонки
| Метод | Путь | Auth | Описание |
|-------|------|------|----------|
| GET | /api/video-calls | ✓ | История звонков |
| POST | /api/video-calls | ✓ | Создать звонок |
| PATCH | /api/video-calls/{id} | ✓ | Обновить статус (active/completed/cancelled) |

### Бейджи
| Метод | Путь | Auth | Описание |
|-------|------|------|----------|
| GET | /api/badges | - | Список бейджей |
| GET | /api/badges/user/{userId} | - | Бейджи пользователя |

---

## Авторизация

Требуемые эндпоинты принимают заголовок:
```
Authorization: Bearer <token>
```
Токен возвращается при `POST /api/auth/login` и `POST /api/auth/register`.

---

## Примеры запросов

**Регистрация:**
```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"ivan","email":"ivan@mail.ru","password":"secret123","full_name":"Иван"}'
```

**Вход:**
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ivan@mail.ru","password":"secret123"}'
```

**Поиск партнёров** (кто учит Python и хочет учить французский):
```bash
curl "http://localhost:8080/api/users/search?teach=1&learn=4"
```
*(teach=id навыка, которому ты учишь; learn=id навыка, которому хочешь научиться)*
