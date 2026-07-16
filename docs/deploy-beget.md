# Деплой «Теннис на Новой Земле» на BeGet (shared)

Боевая инструкция по выкатке на BeGet shared-хостинг. Стек: `backend/` — Symfony 7.4,
`frontend/` — React/Vite, MySQL 8. Репозиторий — на GitHub.

Плейсхолдеры:
- `<DOMAIN>` — каталог сайта, у нас **`tnz.deemmoor.beget.tech`**;
- `<REPO>` — git-адрес (напр. `git@github.com:deemmoor/tnz.git`);
- `<DB>` — имя БД (на BeGet имя пользователя БД совпадает с именем базы).

Сервер: домашний каталог `~` = `/home/d/deemmoor`. Каталог сайта — `~/<DOMAIN>`.

---

## Особенности BeGet (важно)

- **PHP вызывается как `php8.5`** (не `php`). Проверка: `php8.5 --version`.
- **Composer:** `php8.5 ~/.local/bin/composer` (2.x).
- **Системный Node старый** — Vite не соберёт. Ставим свежий через **nvm** (шаг 1).
- **Веб-корень домена жёстко `~/<DOMAIN>/public_html`** — в панели не сменить. Обходим **симлинком** на `app/backend/public` (шаг 3).
- Есть SSH/bash, MySQL-клиент, git.

Удобные алиасы на сессию:
```bash
alias php='php8.5'
alias composer='php8.5 ~/.local/bin/composer'
```

## Целевая топология на сервере

```
~/<DOMAIN>/
├── app/                       ← git-репозиторий проекта
│   ├── backend/   (Symfony: src, config, vendor, public/ …)
│   ├── frontend/  (React/Vite → собирается в ../backend/public)
│   └── docs/
├── public_html      →  app/backend/public      (СИМЛИНК — docroot)
└── public_html_old  ←  дефолтная папка (бэкап)
```
Код лежит **выше** docroot → `src`, `.env.local` с паролями из веба недоступны.

---

# Первичная установка

## 1. Свежий Node через nvm
```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
export NVM_DIR="$HOME/.nvm"; [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
nvm install --lts
node --version    # 20+
```

## 2. Deploy key для GitHub
```bash
ssh-keygen -t ed25519 -C "beget-deploy" -f ~/.ssh/github_deploy -N ""
cat ~/.ssh/github_deploy.pub        # скопировать строку ssh-ed25519 ...
```
Добавить **публичный** ключ: GitHub → репозиторий → **Settings → Deploy keys → Add** (read-only достаточно).

Сказать ssh использовать ключ для GitHub:
```bash
printf 'Host github.com\n    HostName github.com\n    User git\n    IdentityFile ~/.ssh/github_deploy\n    IdentitiesOnly yes\n' >> ~/.ssh/config
chmod 600 ~/.ssh/config
ssh -T git@github.com               # "Hi deemmoor/tnz! You've successfully authenticated"
```

## 3. Клонировать и смапить docroot симлинком
```bash
cd ~/<DOMAIN>
mv public_html public_html_old
git clone <REPO> app
ln -s app/backend/public public_html
ls -l public_html                   # public_html -> app/backend/public
```
> Если сайт отдаёт 403 — в `app/backend/public/.htaccess` уже есть `Options +FollowSymLinks` (должно решать).

## 4. Создать БД (в панели BeGet)
Раздел **MySQL** → создать базу + пользователя (имя пользователя = имя базы, с префиксом логина, напр. `deemmoor_tnz`), задать пароль. Хост — `localhost`.

## 5. `app/backend/.env.local` (на сервере, в git НЕ идёт)
```bash
cd ~/<DOMAIN>/app/backend
php8.5 -r "echo bin2hex(random_bytes(16)), \"\n\";"   # APP_SECRET
nano .env.local
```
Содержимое:
```dotenv
APP_ENV=prod
APP_SECRET=<строка_из_команды_выше>

DATABASE_URL="mysql://<DB>:<ПАРОЛЬ>@localhost:3306/<DB>?serverVersion=8.0&charset=utf8mb4"

# Почта BeGet (SMTP). Ящик создать в панели (раздел «Почта»).
MAILER_DSN="smtp://<ящик>%40<DOMAIN>:<пароль_ящика>@smtp.beget.com:465?encryption=ssl"
MAILER_FROM="<ящик>@<DOMAIN>"

# База для ссылок в письмах — адрес сайта (https).
APP_PUBLIC_URL="https://<DOMAIN>"

# Подмена времени — только для отладки. На проде оставить пустым!
APP_FAKE_NOW=
```
- `<DB>` стоит и пользователем (до `:`), и базой (после `/`).
- Спецсимволы в паролях **url-кодировать** (`@`→`%40`, `#`→`%23`, `%`→`%25`).
- `serverVersion` — под реальную СУБД (MariaDB → напр. `10.11.2-MariaDB`).
- `APP_ENV=prod` обязателен **до** `composer install`.

## 6. PHP-зависимости (prod)
```bash
cd ~/<DOMAIN>/app/backend
php8.5 ~/.local/bin/composer install --no-dev --optimize-autoloader
```

## 7. Собрать фронт
```bash
cd ~/<DOMAIN>/app/frontend
npm ci
npm run build          # собирается в ../backend/public (index.html, assets/)
```

## 8. Схема БД и админ
```bash
cd ~/<DOMAIN>/app/backend
php8.5 bin/console doctrine:migrations:migrate --no-interaction
php8.5 bin/console app:create-admin "<телефон>" "<пароль>" "<Фамилия Имя>"
```
> Демо-данные (`app:seed-demo`) на прод **не** льём — реальные турниры создаются в админке.

## 9. Права на запись
`app/backend/var/` (кэш, логи) — доступен на запись веб-серверу.

## 10. Создать первый турнир
Зайти `https://<DOMAIN>/admin` под админом → **Турниры → создать**, задать дату (воскресенье), статус `registration`.

## 11. Проверить
- `https://<DOMAIN>/` — открывается SPA;
- `https://<DOMAIN>/api/ping` — `{"status":"ok","db":true}`;
- `https://<DOMAIN>/admin` — вход админом;
- регистрация игрока → запись на турнир (когда окно открыто).

---

# Последующие обновления

Через Makefile локально: `make deploy` (тесты → git push → git pull + сборка на сервере).

Или руками на сервере:
```bash
cd ~/<DOMAIN>/app && git pull && bash scripts/deploy-remote.sh
```
`scripts/deploy-remote.sh` делает: `composer install --no-dev` → миграции → `cache:clear` → `npm ci && npm run build`.

---

# Грабли

- **`APP_ENV=prod` до `composer install`** — иначе авто-скрипт `cache:clear` идёт в dev и падает на отсутствующих dev-бандлах.
- **Docroot не сменить в панели** → симлинк `public_html → app/backend/public`.
- **Старый Node** → nvm.
- **403 после симлинка** → `Options +FollowSymLinks` (уже в `.htaccess`).
- **heredoc залипает при вставке** в терминал → используй `nano`/`printf`.
- **Письма не уходят** → проверь `MAILER_DSN` (порт/шифрование BeGet), ящик существует, пароль url-кодирован.

---

# Чек-лист безопасности

- [ ] `APP_ENV=prod`, `APP_FAKE_NOW=` (пусто).
- [ ] `APP_SECRET` — свой, не из git.
- [ ] Боевые DB/SMTP-креды только в `.env.local`.
- [ ] Код выше docroot (симлинк) — `.env.local`/`src` из веба недоступны.
- [ ] HTTPS включён → cookie сессии автоматически `Secure`.
- [ ] Админ создан командой; дефолтных `admin/admin` нет.
