# Короткие команды, чтобы не печатать длинные docker compose ... вручную.
# Примеры:  make up   make sh   make composer c="require orm"

.PHONY: up down restart sh logs composer console test test-db front-build deploy

up:            ## Поднять все контейнеры в фоне
	docker compose up -d

down:          ## Остановить и убрать контейнеры
	docker compose down

restart:       ## Перезапустить
	docker compose restart

sh:            ## Зайти внутрь php-контейнера (bash) под пользователем dev
	docker compose exec --user dev php bash

logs:          ## Смотреть логи всех контейнеров
	docker compose logs -f

composer:      ## Запустить composer внутри php:  make composer c="require orm"
	docker compose run --rm --user dev php composer $(c)

console:       ## Symfony-консоль:  make console c="about"
	docker compose run --rm --user dev php php bin/console $(c)

test:          ## Прогнать тесты (PHPUnit). Перед деплоем — обязательно.
	docker compose exec --user dev php sh -c 'cd backend && php bin/phpunit'

test-db:       ## Один раз: создать и мигрировать тестовую БД nz_test
	docker compose exec db sh -c "mysql -uroot -proot -e \"CREATE DATABASE IF NOT EXISTS nz_test; GRANT ALL PRIVILEGES ON nz_test.* TO 'nz'@'%'; FLUSH PRIVILEGES;\""
	docker compose exec --user dev php sh -c 'cd backend && php bin/console --env=test doctrine:migrations:migrate --no-interaction'

front-build:   ## Собрать React в backend/public (для прода)
	docker compose exec -w /app/frontend node npm run build

deploy:        ## Деплой на BeGet: тесты -> git push -> сервер git pull + обновление
	@echo ">> 1/3 тесты"
	$(MAKE) test
	@echo ">> 2/3 git push"
	git push origin master
	@echo ">> 3/3 деплой на сервере"
	ssh deemmoor 'cd ~/tnnz.ru/app && git pull && bash scripts/deploy-remote.sh'
