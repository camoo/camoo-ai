PROJECT_NAME=ctrl-alt-defeat
CONTAINER=camoo-ai

.PHONY: help up down build shell install migrate migrate-test fixtures test lint coverage deptrac

help:
	@echo ""
	@echo "Usage:"
	@echo "  make up               - Build and start containers"
	@echo "  make down             - Stop containers"
	@echo "  make build            - Rebuild containers"
	@echo "  make shell            - Open a shell inside the PHP container"
	@echo "  make install          - Install dependencies (inside container)"
	@echo "  make migrate          - Run migrations"
	@echo "  make migrate-test     - Run migrations for test env"
	@echo "  make fixtures         - Clear cache for test env"
	@echo "  make test             - Run unit tests"
	@echo "  make lint             - Run PHP CS Fixer / Linter"
	@echo "  make coverage         - Open code coverage in browser"
	@echo "  make analyse          - Run Deptrac analysis"
	@echo ""

up:
	docker-compose up -d --build

down:
	docker-compose down

build:
	docker-compose build

shell bash:
	docker-compose exec $(CONTAINER) bash

install:
	docker-compose exec $(CONTAINER) composer install

migrate:
	docker-compose exec $(CONTAINER) php bin/console doctrine:migrations:migrate --no-interaction

migrate-test:
	docker-compose exec $(CONTAINER) php bin/console doctrine:migrations:migrate --env=test --no-interaction

fixtures:
	docker-compose exec $(CONTAINER) php bin/console cache:clear --env=test

test:
	docker-compose exec $(CONTAINER) composer test

lint:
	docker-compose exec $(CONTAINER) composer lint

coverage:
	open http://localhost:8383/coverage/

analyse deptrac:
	docker-compose exec $(CONTAINER) composer analyse
