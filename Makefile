PUID :=$(shell id -u)
PGID :=$(shell id -g)
COMPOSE := PUID=$(PIUD) PGID=$(PGID) docker compose

.DEFAULT_GOAL := help
.PHONY: help up down build fresh xdebug shell logs test migrate seed artisan composer schema

help: ## Show the available targets
	@grep -hE '^[a-z-]+:.*##' $(MAKEFILE_LIST) | sed -e 's/:.*## /|/' | column -t -s '|'

up: ## Start every container and wait until all are healthy 
	$(COMPOSE) up -d --wait

down: ## Stop the containers, keep the database
	$(COMPOSE) down

build: ## Rebuild the image, then start and wait
	$(COMPOSE) up -d --build --wait

fresh: ## Drop the database volume and start from scratch — DESTROYS ALL DATA
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build --wait

xbedug: ## Start with Xdebug enabled
	XDEBUG_MODE=debug $(COMPOSE) up -d --wait

shell: ## Open a shell inside the app container
	$(COMPOSE) exec app sh

logs: ## Follow the logs of every container
	$(COMPOSE) logs -f

test: ## Run the test suite against the separate test database
	$(COMPOSE) exec app php artisan test

migrate: ## Run outstanding migrations
	$(COMPOSE) exec app php artisan migrate

seed: ## Create the demo user
	$(COMPOSE) exec app php artisan db:seed

artisan: ## Run any artisan command — make artisan COMMAND="route:list"
	$(COMPOSE) exec app php artisan $(COMMAND)

composer: ## Run composer inside the container (COMMAND overrides the action)
	$(COMPOSE) exec app composer $(or $(COMMAND),install)

schema: ## Dump the structure of the application tables into database/schema.sql
	$(COMPOSE) exec app sh -c 'mysqldump --skip-ssl --no-data --skip-comments --no-tablespaces -h mysql -u "$$DB_USERNAME" -p"$$DB_PASSWORD" "$$DB_DATABASE" users imports import_rows | sed -e "s/ AUTO_INCREMENT=[0-9]*//" > database/schema.sql'
