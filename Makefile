##
## Multi-site Symfony — gestion par site via .env.SITE
##
## Usage :
##   make up SITE=unetaupechezvous
##   make up-unetaupechezvous
##   make init SITE=maximefreelance       # up + migrate + fixtures
##   make console CMD="cache:clear" SITE=unetaupechezvous
##

SITE ?= unetaupechezvous

# Dev : compose.yaml + compose.override.yaml (php -S + mailhog)
DOCKER_COMPOSE = docker compose \
	-p $(SITE) \
	--env-file .env.$(SITE) \
	-f docker/compose.yaml \
	-f docker/compose.override.yaml

# Prod : compose.yaml uniquement (php-fpm + nginx, sans mailhog)
DOCKER_COMPOSE_PROD = docker compose \
	-p $(SITE) \
	--env-file .env.$(SITE) \
	-f docker/compose.yaml \
	--profile prod

PHP_SERVICE = app
PHP_SERVICE_PROD = php

ENV_FILE = .env.$(SITE)

# ── Validation ────────────────────────────────────────────────────

check-env:
	@if [ ! -f "$(ENV_FILE)" ]; then \
		echo "ERREUR : $(ENV_FILE) introuvable."; \
		echo "Copiez .env.$(SITE).dist en .env.$(SITE) et remplissez-le."; \
		exit 1; \
	fi

# ── Docker ────────────────────────────────────────────────────────

up: check-env ## Lance les conteneurs (SITE=...)
	$(DOCKER_COMPOSE) up -d --build

down: ## Arrête les conteneurs (SITE=...)
	$(DOCKER_COMPOSE) down --remove-orphans

restart: down up ## Redémarre (SITE=...)

logs: ## Logs en continu (SITE=...)
	$(DOCKER_COMPOSE) logs -f $(PHP_SERVICE)

ps: ## Liste les conteneurs du site (SITE=...)
	$(DOCKER_COMPOSE) ps

bash: check-env ## Shell bash dans le conteneur PHP (SITE=...)
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) bash

# ── Symfony ───────────────────────────────────────────────────────

console: check-env ## Commande Symfony : make console CMD="cache:clear" SITE=...
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console $(CMD)

migrate: check-env ## Migrations (SITE=...)
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

fixtures: check-env ## Charge les fixtures du site (SITE=...)
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:fixtures:load --no-interaction

db-reset: check-env ## Drop + create + migrate + fixtures (SITE=...)
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:database:drop --force --if-exists
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:database:create
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:fixtures:load --no-interaction

init: up ## Lance, migre et charge les fixtures (SITE=...)
	@sleep 5
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:fixtures:load --no-interaction
	@echo ""
	@echo "Site '$(SITE)' prêt."

# ── Raccourcis par site ───────────────────────────────────────────

up-unetaupechezvous:
	$(MAKE) up SITE=unetaupechezvous

down-unetaupechezvous:
	$(MAKE) down SITE=unetaupechezvous

init-unetaupechezvous:
	$(MAKE) init SITE=unetaupechezvous

fixtures-unetaupechezvous:
	$(MAKE) fixtures SITE=unetaupechezvous

db-reset-unetaupechezvous:
	$(MAKE) db-reset SITE=unetaupechezvous

up-maximefreelance:
	$(MAKE) up SITE=maximefreelance

down-maximefreelance:
	$(MAKE) down SITE=maximefreelance

init-maximefreelance:
	$(MAKE) init SITE=maximefreelance

fixtures-maximefreelance:
	$(MAKE) fixtures SITE=maximefreelance

db-reset-maximefreelance:
	$(MAKE) db-reset SITE=maximefreelance

# ─────────────────────────────────────────────────────────────────

# ── Prod ─────────────────────────────────────────────────────────

up-prod: check-env ## [PROD] Lance php-fpm + nginx + database (SITE=...)
	$(DOCKER_COMPOSE_PROD) up -d --build

down-prod: ## [PROD] Arrête les conteneurs prod (SITE=...)
	$(DOCKER_COMPOSE_PROD) down --remove-orphans

migrate-prod: check-env ## [PROD] Migrations en prod (SITE=...)
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:migrations:migrate --no-interaction --env=prod

db-reset-prod: check-env ## [PROD] Drop + create + migrate en prod (SITE=...)
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:database:drop --force --if-exists --env=prod
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:database:create --env=prod
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:migrations:migrate --no-interaction --env=prod

console-prod: check-env ## [PROD] Commande Symfony en prod : make console-prod CMD="cache:clear" SITE=...
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console $(CMD) --env=prod

logs-prod: ## [PROD] Logs en continu (SITE=...)
	$(DOCKER_COMPOSE_PROD) logs -f $(PHP_SERVICE_PROD)

# ── Raccourcis prod — unetaupechezvous ───────────────────────────

up-prod-unetaupechezvous:
	$(MAKE) up-prod SITE=unetaupechezvous

down-prod-unetaupechezvous:
	$(MAKE) down-prod SITE=unetaupechezvous

# ── Raccourcis prod — maximefreelance ────────────────────────────

up-prod-maximefreelance:
	$(MAKE) up-prod SITE=maximefreelance

down-prod-maximefreelance:
	$(MAKE) down-prod SITE=maximefreelance

# ─────────────────────────────────────────────────────────────────

list-sites: ## Liste les .env.SITE disponibles
	@ls .env.*.dist 2>/dev/null | sed 's/\.env\.\(.*\)\.dist/  \1 (dist)/' || true
	@ls .env.* 2>/dev/null | grep -v '\.dist$$' | grep -v '\.local$$' | grep -v '^\.env$$' | sed 's/\.env\./  /' || true

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-28s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
.PHONY: check-env up down restart logs ps bash console migrate fixtures db-reset init \
	up-unetaupechezvous down-unetaupechezvous init-unetaupechezvous fixtures-unetaupechezvous db-reset-unetaupechezvous \
	up-maximefreelance down-maximefreelance init-maximefreelance fixtures-maximefreelance db-reset-maximefreelance \
	up-prod down-prod migrate-prod db-reset-prod console-prod logs-prod \
	up-prod-unetaupechezvous down-prod-unetaupechezvous \
	up-prod-maximefreelance down-prod-maximefreelance \
	list-sites help
