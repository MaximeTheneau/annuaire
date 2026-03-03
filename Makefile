##
## Multi-site Symfony — gestion par site via .env.SITE
##
## Usage :
##   make up SITE=unetaupechezvous          # dev
##   make up-prod SITE=unetaupechezvous     # prod (composer install inclus dans l'image)
##   make init-prod SITE=unetaupechezvous   # prod : up + migrate + fixtures + cache
##   make fixtures-prod SITE=unetaupechezvous
##   make console CMD="cache:clear" SITE=unetaupechezvous
##

SITE ?= unetaupechezvous

# ── Dev : compose.yaml + compose.override.yaml (php -S + mailhog) ─
DOCKER_COMPOSE = docker compose \
	-p $(SITE) \
	--env-file .env.$(SITE) \
	-f docker/compose.yaml \
	-f docker/compose.override.yaml

# ── Prod : compose.yaml + compose.prod.yaml (php-fpm + nginx) ─────
DOCKER_COMPOSE_PROD = docker compose \
	-p $(SITE) \
	--env-file .env.$(SITE) \
	-f docker/compose.yaml \
	-f docker/compose.prod.yaml

# ── Fixtures prod : même stack prod + profile fixtures ────────────
DOCKER_COMPOSE_FIXTURES = docker compose \
	-p $(SITE) \
	--env-file .env.$(SITE) \
	-f docker/compose.yaml \
	-f docker/compose.prod.yaml \
	--profile fixtures

PHP_SERVICE          = app
PHP_SERVICE_PROD     = php
PHP_SERVICE_FIXTURES = php-fixtures

ENV_FILE = .env.$(SITE)

# ── Validation ────────────────────────────────────────────────────

check-env:
	@if [ ! -f "$(ENV_FILE)" ]; then \
		echo "ERREUR : $(ENV_FILE) introuvable."; \
		echo "Lancez : make new-site SITE=$(SITE)"; \
		exit 1; \
	fi

new-site: ## Crée .env.SITE depuis le template (SITE=...)
	@if [ -f "$(ENV_FILE)" ]; then \
		echo "$(ENV_FILE) existe déjà — supprimez-le d'abord si vous voulez le recréer."; \
		exit 1; \
	fi
	@sed 's/monsite/$(SITE)/g' .env.site.dist > $(ENV_FILE)
	@mkdir -p fixtures/$(SITE)
	@echo "✓ $(ENV_FILE) créé (pensez à renseigner les mots de passe et APP_SECRET)"
	@echo "✓ fixtures/$(SITE)/ créé"

# ── Docker DEV ────────────────────────────────────────────────────

up: check-env ## [DEV] Lance les conteneurs dev (SITE=...)
	$(DOCKER_COMPOSE) up -d --build

down: ## [DEV] Arrête les conteneurs dev (SITE=...)
	$(DOCKER_COMPOSE) down --remove-orphans

restart: down up ## [DEV] Redémarre (SITE=...)

logs: ## [DEV] Logs en continu (SITE=...)
	$(DOCKER_COMPOSE) logs -f $(PHP_SERVICE)

ps: ## Liste les conteneurs du site (SITE=...)
	$(DOCKER_COMPOSE) ps

bash: check-env ## [DEV] Shell bash dans le conteneur PHP (SITE=...)
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) bash

# ── Symfony DEV ───────────────────────────────────────────────────

console: check-env ## [DEV] Commande Symfony : make console CMD="cache:clear" SITE=...
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console $(CMD)

migrate: check-env ## [DEV] Migrations (SITE=...)
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

fixtures: check-env ## [DEV] Charge les fixtures du site (SITE=...)
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:fixtures:load --no-interaction

db-reset: check-env ## [DEV] Drop + create + migrate + fixtures (SITE=...)
	$(DOCKER_COMPOSE) exec database sh -c \
		"mysql -uroot -p\$${MYSQL_ROOT_PASSWORD} -e \
		'DROP DATABASE IF EXISTS \`'\$${MYSQL_DATABASE}'\`; \
		 CREATE DATABASE \`'\$${MYSQL_DATABASE}'\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
		 GRANT ALL ON \`'\$${MYSQL_DATABASE}'\`.* TO \"'\$${MYSQL_USER}'\"@\"%\";'"
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:fixtures:load --no-interaction

init: up ## [DEV] Lance, migre et charge les fixtures (SITE=...)
	@echo "Attente de MySQL..."
	@$(DOCKER_COMPOSE) exec database sh -c 'until mysqladmin ping -h 127.0.0.1 -u$$MYSQL_USER -p$$MYSQL_PASSWORD --silent; do sleep 1; done'
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE) exec $(PHP_SERVICE) php bin/console doctrine:fixtures:load --no-interaction
	@echo ""
	@echo "Site '$(SITE)' prêt (dev)."

# ── Docker PROD ───────────────────────────────────────────────────

up-prod: check-env ## [PROD] Build + lance php-fpm, nginx, database (SITE=...)
	$(DOCKER_COMPOSE_PROD) up -d --build

down-prod: ## [PROD] Arrête les conteneurs prod (SITE=...)
	$(DOCKER_COMPOSE_PROD) down --remove-orphans

restart-prod: down-prod up-prod ## [PROD] Redémarre (SITE=...)

logs-prod: ## [PROD] Logs en continu (SITE=...)
	$(DOCKER_COMPOSE_PROD) logs -f $(PHP_SERVICE_PROD)

bash-prod: check-env ## [PROD] Shell sh dans le conteneur PHP (SITE=...)
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) sh

# ── Symfony PROD ──────────────────────────────────────────────────

console-prod: check-env ## [PROD] Commande Symfony : make console-prod CMD="cache:clear" SITE=...
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console $(CMD)

migrate-prod: check-env ## [PROD] Migrations en prod (SITE=...)
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:migrations:migrate --no-interaction

cache-prod: check-env ## [PROD] Réchauffe le cache Symfony (SITE=...)
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console cache:warmup

fixtures-prod: check-env ## [PROD] Charge les fixtures (image with-dev, profile:fixtures) (SITE=...)
	$(DOCKER_COMPOSE_FIXTURES) run --rm --build $(PHP_SERVICE_FIXTURES) \
		php bin/console doctrine:fixtures:load --no-interaction

db-grant-prod: check-env ## [PROD] Recrée l'user MySQL avec le bon mot de passe (fix 1045) (SITE=...)
	$(DOCKER_COMPOSE_PROD) exec database sh -c \
		"mysql -h 127.0.0.1 -uroot -p\$${MYSQL_ROOT_PASSWORD} -e \
		\"CREATE USER IF NOT EXISTS '\$${MYSQL_USER}'@'%' IDENTIFIED BY '\$${MYSQL_PASSWORD}'; \
		 ALTER USER '\$${MYSQL_USER}'@'%' IDENTIFIED BY '\$${MYSQL_PASSWORD}'; \
		 GRANT ALL PRIVILEGES ON \\\`\$${MYSQL_DATABASE}\\\`.* TO '\$${MYSQL_USER}'@'%'; \
		 FLUSH PRIVILEGES;\""

volume-reset-prod: check-env ## [PROD] ⚠ Supprime le volume MySQL et repart de zéro (SITE=...)
	$(DOCKER_COMPOSE_PROD) down --remove-orphans
	docker volume rm $(SITE)_database_data || true
	$(MAKE) init-prod SITE=$(SITE)

db-reset-prod: check-env ## [PROD] Drop + create + migrate en prod (SITE=...)
	$(DOCKER_COMPOSE_PROD) exec database sh -c \
		"mysql -uroot -p\$${MYSQL_ROOT_PASSWORD} -e \
		'DROP DATABASE IF EXISTS \`'\$${MYSQL_DATABASE}'\`; \
		 CREATE DATABASE \`'\$${MYSQL_DATABASE}'\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
		 GRANT ALL ON \`'\$${MYSQL_DATABASE}'\`.* TO \"'\$${MYSQL_USER}'\"@\"%\";'"
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:migrations:migrate --no-interaction

deploy-prod: up-prod ## [PROD] Déploie : up + migrate + cache (sans fixtures) (SITE=...)
	@echo "Attente de MySQL..."
	@$(DOCKER_COMPOSE_PROD) exec database sh -c 'until mysqladmin ping -h 127.0.0.1 -u$$MYSQL_USER -p$$MYSQL_PASSWORD --silent; do sleep 1; done'
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console cache:warmup
	@echo ""
	@echo "Site '$(SITE)' déployé en prod."

init-prod: up-prod ## [PROD] Premier déploiement : up + migrate + fixtures + cache (SITE=...)
	@echo "Attente de MySQL..."
	@$(DOCKER_COMPOSE_PROD) exec database sh -c 'until mysqladmin ping -h 127.0.0.1 -u$$MYSQL_USER -p$$MYSQL_PASSWORD --silent; do sleep 1; done'
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE_FIXTURES) run --rm --build $(PHP_SERVICE_FIXTURES) \
		php bin/console doctrine:fixtures:load --no-interaction
	$(DOCKER_COMPOSE_PROD) exec $(PHP_SERVICE_PROD) php bin/console cache:warmup
	@echo ""
	@echo "Site '$(SITE)' initialisé en prod."

# ── Raccourcis — unetaupechezvous ────────────────────────────────

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

up-prod-unetaupechezvous:
	$(MAKE) up-prod SITE=unetaupechezvous

down-prod-unetaupechezvous:
	$(MAKE) down-prod SITE=unetaupechezvous

init-prod-unetaupechezvous:
	$(MAKE) init-prod SITE=unetaupechezvous

fixtures-prod-unetaupechezvous:
	$(MAKE) fixtures-prod SITE=unetaupechezvous

# ── Raccourcis — maximefreelance ──────────────────────────────────

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

up-prod-maximefreelance:
	$(MAKE) up-prod SITE=maximefreelance

down-prod-maximefreelance:
	$(MAKE) down-prod SITE=maximefreelance

init-prod-maximefreelance:
	$(MAKE) init-prod SITE=maximefreelance

fixtures-prod-maximefreelance:
	$(MAKE) fixtures-prod SITE=maximefreelance

# ── Utilitaires ───────────────────────────────────────────────────

list-sites: ## Liste les .env.SITE disponibles
	@ls .env.*.dist 2>/dev/null | sed 's/\.env\.\(.*\)\.dist/  \1 (dist)/' || true
	@ls .env.* 2>/dev/null | grep -v '\.dist$$' | grep -v '\.local$$' | grep -v '^\.env$$' | sed 's/\.env\./  /' || true

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-30s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
.PHONY: check-env new-site \
	up down restart logs ps bash console migrate fixtures db-reset init \
	up-prod down-prod restart-prod logs-prod bash-prod \
	console-prod migrate-prod cache-prod fixtures-prod db-reset-prod deploy-prod init-prod \
	up-unetaupechezvous down-unetaupechezvous init-unetaupechezvous fixtures-unetaupechezvous db-reset-unetaupechezvous \
	up-prod-unetaupechezvous down-prod-unetaupechezvous init-prod-unetaupechezvous fixtures-prod-unetaupechezvous \
	up-maximefreelance down-maximefreelance init-maximefreelance fixtures-maximefreelance db-reset-maximefreelance \
	up-prod-maximefreelance down-prod-maximefreelance init-prod-maximefreelance fixtures-prod-maximefreelance \
	list-sites help
