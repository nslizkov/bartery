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

2. **Запусти контейнеры**
   ```bash
   docker compose up -d
   ```

3. **Проверь работу**
   - Веб-версия: http://localhost:8080 (логин, регистрация, пользователи, сообщения, мой профиль)
   - Админка (БД): http://localhost:8080/admin/
   - API: http://localhost:8080/api/categories или `curl http://localhost:8080/api/categories`

4. **Остановка**
   ```bash
   docker compose down
   ```

## Развёртывание на удалённом сервере

### Вариант 1: Docker Compose

1. **Скопируй проект на сервер**

2. **Запусти**
   ```bash
   docker compose up -d
   ```

