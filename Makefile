include .env
export

.PHONY: up down restart logs build psql migrate

up:
	docker compose up -d

down:
	docker compose down -v

restart: down up

logs:
	docker compose logs -f

build:
	docker compose build --no-cache && docker compose up -d

psql:
	docker exec -it netmanager_db psql -U $(DB_USER) -d $(DB_NAME)

## Usage: make migrate FILE=db/migrations/007_app_settings.sql
migrate:
	docker exec -i netmanager_db psql -U $(DB_USER) -d $(DB_NAME) < $(FILE)
