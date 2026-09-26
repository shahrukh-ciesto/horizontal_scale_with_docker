# Laravel Docker Project

A Laravel project running with Docker, Nginx, MySQL and Redis.

I have used two Laravel app containers behind Nginx to test basic load balancing and horizontal scaling.

The project also covers both **stateful and stateless authentication**:

* Laravel Sanctum for session/cookie based authentication
* JWT for stateless API authentication
* Redis for centralized sessions and cache
* k6 for basic load testing

---

## How It Works

```text
                    Client
                      |
                      v
               Nginx Load Balancer
                  /           \
                 /             \
                v               v
             app-1           app-2
                \               /
                 \             /
                  v           v
                    Redis
                 /         \
            Sessions      Cache
                      |
                      v
                    MySQL
```

Nginx receives the request and sends it to one of the Laravel containers.

```text
Request → Nginx → app-1
Request → Nginx → app-2
```

Both app containers use the same MySQL database and Redis instance.

---

# Project Setup

## Requirements

Install:

* Docker
* Docker Compose
* Git
* k6 (only required for load testing)

Check Docker:

```bash
docker --version
docker compose version
```

---

## 1. Clone Project

```bash
git clone <repository-url>
cd <project-folder>
```

---

## 2. Create `.env`

```bash
cp .env.example .env
```

For Windows:

```powershell
copy .env.example .env
```

Update the database settings:

```env
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
```

Redis:

```env
REDIS_HOST=redis
REDIS_PORT=6379
SESSION_DRIVER=redis
```

> Inside Docker, use `mysql` and `redis` as the hostnames because these are the Docker service names.

---

## 3. Start Docker

```bash
docker compose up -d --build
```

Check containers:

```bash
docker compose ps
```

You should see:

```text
app-1
app-2
nginx
mysql
redis
```

---

## 4. Laravel Setup

Generate application key:

```bash
docker compose exec app-1 php artisan key:generate
```

Run migrations:

```bash
docker compose exec app-1 php artisan migrate
```

Clear Laravel cache:

```bash
docker compose exec app-1 php artisan optimize:clear
```

---

## 5. Open Project

Open:

```text
http://localhost:8080
```

---

# Redis Session

Both Laravel containers use the same Redis server.

```text
app-1 ──┐
        ├── Redis
app-2 ──┘
```

Laravel session configuration:

```env
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379
```

For example:

```text
Login
  ↓
app-1
  ↓
Redis Session
  ↓
Next Request
  ↓
app-2
  ↓
Same Session
```

So the user does not lose the session when Nginx sends the next request to another application container.

---

# Authentication

## Stateful Authentication - Sanctum

Sanctum is used for session/cookie based authentication.

```text
Login
  ↓
Laravel Session
  ↓
Redis
  ↓
Authenticated Request
```

This is useful when the application needs server-side session state.

---

## Stateless Authentication - JWT

JWT is used for API authentication without storing the login session on the server.

```text
Login
  ↓
JWT Token
  ↓
Client
  ↓
Authorization: Bearer <token>
  ↓
API
```

The API validates the JWT on each request.

---

# Load Balancing

Nginx sits in front of both Laravel containers.

```text
Client
  |
  v
Nginx
  |
  +---- app-1
  |
  +---- app-2
```

A simple health endpoint can be used to check which container handled the request.

Example:

```php
Route::get('/health', function () {
    return [
        'status' => 'ok',
        'server' => gethostname()
    ];
});
```

Open:

```text
http://localhost:8080/health
```

You may get:

```json
{
    "status": "ok",
    "server": "app-1"
}
```

and another request may be:

```json
{
    "status": "ok",
    "server": "app-2"
}
```

This makes it easy to verify that both application containers are running.

---

# Load Testing

I use k6 for basic load testing.

Example:

```javascript
import http from 'k6/http';

export const options = {
    vus: 50,
    duration: '30s',
};

export default function () {
    http.get('http://localhost:8080/health');
}
```

Run:

```bash
k6 run tests/load-test.js
```

While the test is running:

```bash
docker stats
```

Check application logs:

```bash
docker compose logs -f app-1 app-2
```

This helps check how both application containers handle concurrent requests.

---

# Useful Commands

Start:

```bash
docker compose up -d --build
```

Stop:

```bash
docker compose down
```

Check containers:

```bash
docker compose ps
```

View logs:

```bash
docker compose logs -f
```

Laravel shell:

```bash
docker compose exec app-1 bash
```

Run Artisan:

```bash
docker compose exec app-1 php artisan
```

Clear cache:

```bash
docker compose exec app-1 php artisan optimize:clear
```

Check Redis:

```bash
docker compose exec redis redis-cli ping
```

Expected:

```text
PONG
```

Check MySQL:

```bash
docker compose exec mysql mysql -ularavel -psecret laravel
```

# Main Things Covered

```text
Laravel
Docker
Nginx Load Balancer
Multiple App Containers
MySQL
Redis
Centralized Sessions
Sanctum
JWT
Stateful Authentication
Stateless Authentication
REST APIs
k6 Load Testing
Basic Horizontal Scaling
```
