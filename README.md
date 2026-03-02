# Annuaire — Symfony multi-sites

Un seul repo, plusieurs sites déployables indépendamment.
Chaque site a son `.env.SITE`, ses fixtures CSV et ses containers Docker isolés.

---

## Prérequis

- Docker + Docker Compose v2
- Make

---

## Démarrage rapide

```bash
# 1. Créer la config du site
cp .env.site.dist .env.unetaupechezvous
# éditer .env.unetaupechezvous

# 2. Lancer (containers + migrations + fixtures)
make init SITE=unetaupechezvous
```

---

## Configuration d'un site

Copier et renommer le template :

```bash
cp .env.site.dist .env.monsite
```

Renseigner dans `.env.monsite` :

| Variable | Description |
|---|---|
| `SITE` | Nom du site (doit correspondre au suffixe du fichier) |
| `HTTP_PORT` | Port web exposé (ex: `8001`) |
| `MYSQL_PORT` | Port MySQL exposé (ex: `3307`) |
| `MYSQL_DATABASE` | Nom de la base |
| `MYSQL_PASSWORD` | Mot de passe MySQL |
| `MYSQL_ROOT_PASSWORD` | Mot de passe root MySQL |
| `APP_SECRET` | Secret Symfony — générer : `php -r "echo bin2hex(random_bytes(16));"` |
| `DATABASE_URL` | `mysql://app:PASSWORD@database:3306/NOM_BASE?serverVersion=8.0.32&charset=utf8mb4` |
| `FIXTURES_DIR` | Dossier CSV à charger, ex: `fixtures/monsite` |
| `MAILER_DSN` | Dev: `smtp://mailhog-monsite:1025` — Prod: votre SMTP réel |

> Les fichiers `.env.*` (sans `.dist`) sont ignorés par git — ne committez jamais vos secrets.

Mettre les CSV dans le dossier correspondant :

```
fixtures/
  monsite/
    categories.csv
    companies_sample.csv
    departments_prefectures.csv
```

---

## Dev

Stack démarré : `php -S` + MySQL + Mailhog

```bash
make init SITE=monsite          # up + migrate + fixtures (premier lancement)
make up SITE=monsite            # démarrer
make down SITE=monsite          # arrêter
make logs SITE=monsite          # logs PHP
make migrate SITE=monsite       # migrations
make fixtures SITE=monsite      # recharger les fixtures
make db-reset SITE=monsite      # drop → create → migrate → fixtures
make bash SITE=monsite          # shell dans le container
make console CMD="cache:clear" SITE=monsite
```

Interface Mailhog : `http://localhost:MAILHOG_UI_PORT` (défaut 8025)

### Plusieurs sites simultanément

Chaque site doit avoir des ports différents dans son `.env.SITE` :

```bash
make up SITE=unetaupechezvous   # → localhost:8001
make up SITE=maximefreelance    # → localhost:8002
```

---

## Prod

Stack démarré : PHP-FPM + Nginx + MySQL — **sans Mailhog**

```bash
make up-prod SITE=monsite       # démarrer
make down-prod SITE=monsite     # arrêter
make migrate-prod SITE=monsite  # migrations
make logs-prod SITE=monsite     # logs PHP-FPM
make db-reset-prod SITE=monsite # drop → create → migrate  ⚠ irréversible
make console-prod CMD="cache:clear" SITE=monsite
```

Penser à mettre un vrai SMTP dans `MAILER_DSN` avant de lancer en prod.

---

## Ajouter un nouveau site

```bash
# Fixtures
mkdir -p fixtures/monsite
cp fixtures/unetaupechezvous/*.csv fixtures/monsite/

# Config
cp .env.site.dist .env.monsite
# éditer .env.monsite

# Lancer
make init SITE=monsite
```

---

## Fichiers clés

| Fichier | Rôle |
|---|---|
| `.env.site.dist` | Template à copier pour chaque nouveau site |
| `fixtures/SITE/` | CSV chargés au `make fixtures` |
| `docker/compose.yaml` | Base : MySQL + PHP-FPM + Nginx (profil `prod`) |
| `docker/compose.override.yaml` | Dev : `php -S` + Mailhog |
| `Makefile` | Toutes les commandes |
