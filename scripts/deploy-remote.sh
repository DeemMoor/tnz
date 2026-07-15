#!/usr/bin/env bash
#
# Запускается НА СЕРВЕРЕ (BeGet) после git pull. Обновляет прод:
# зависимости, миграции, кэш, сборка фронта. Локально не запускать.
#
set -euo pipefail

# Перейти в корень репозитория (app/), где бы скрипт ни вызвали.
cd "$(dirname "$0")/.."

echo ">> composer install (--no-dev)"
cd backend
php8.5 ~/.local/bin/composer install --no-dev --optimize-autoloader

echo ">> миграции БД"
php8.5 bin/console doctrine:migrations:migrate --no-interaction

echo ">> чистка кэша"
php8.5 bin/console cache:clear

echo ">> сборка фронта"
cd ../frontend
export NVM_DIR="$HOME/.nvm"
# shellcheck disable=SC1091
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
npm ci
npm run build

echo ">> деплой завершён"
