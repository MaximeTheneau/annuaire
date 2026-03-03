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

# 2. Dev — up + migrate + fixtures
make init SITE=unetaupechezvous

# 2. Prod — up (composer install inclus) + migrate + fixtures + cache
make init-prod SITE=unetaupechezvous
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

Stack : `php -S` + MySQL + Mailhog. Le code est monté en volume (hot-reload).

```bash
make init SITE=monsite          # premier lancement : up + migrate + fixtures
make up SITE=monsite            # démarrer
make down SITE=monsite          # arrêter
make restart SITE=monsite       # redémarrer
make logs SITE=monsite          # logs PHP
make bash SITE=monsite          # shell dans le container
make migrate SITE=monsite       # migrations
make fixtures SITE=monsite      # recharger les fixtures
make db-reset SITE=monsite      # drop → create → migrate → fixtures
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

Stack : PHP-FPM + Nginx + MySQL — **sans Mailhog**.
Le `composer install --no-dev` est exécuté **lors du build de l'image** (pas besoin de vendor local).

### Workflows

**Premier déploiement** (up + migrate + fixtures + cache) :
```bash
make init-prod SITE=monsite
```

**Redéploiement** (rebuild image + migrate + cache, sans fixtures) :
```bash
make deploy-prod SITE=monsite
```

**Commandes individuelles** :
```bash
make up-prod SITE=monsite            # build + démarrer
make down-prod SITE=monsite          # arrêter
make restart-prod SITE=monsite       # redémarrer
make logs-prod SITE=monsite          # logs PHP-FPM
make bash-prod SITE=monsite          # shell sh dans le container
make migrate-prod SITE=monsite       # migrations
make fixtures-prod SITE=monsite      # charger les fixtures ⚠ efface les données existantes
make cache-prod SITE=monsite         # cache:warmup
make db-reset-prod SITE=monsite      # drop → create → migrate  ⚠ irréversible
make console-prod CMD="cache:clear" SITE=monsite
```

> `fixtures-prod` utilise une image dédiée avec les deps de dev (`doctrine-fixtures-bundle`).
> Elle est isolée du container PHP de prod — aucun impact sur le runtime.

Mettre un vrai SMTP dans `MAILER_DSN` avant de lancer en prod.

---

## Ajouter un nouveau site

```bash
# Créer les fixtures
mkdir -p fixtures/monsite
cp fixtures/unetaupechezvous/*.csv fixtures/monsite/

# Créer la config
make new-site SITE=monsite
# éditer .env.monsite (ports, passwords, APP_SECRET, DATABASE_URL)

# Lancer
make init SITE=monsite        # dev
make init-prod SITE=monsite   # prod
```

---

## Troubleshooting

### `Access denied for user 'app'` au migrate (erreur 1045)

MySQL refuse la connexion. Causes possibles dans l'ordre de probabilité :

**1. `MYSQL_PASSWORD` et `DATABASE_URL` ne correspondent pas dans `.env.SITE`**

```
MYSQL_PASSWORD=monmotdepasse
DATABASE_URL=mysql://app:monmotdepasse@database:3306/monsite?...
#                        ^^^^^^^^^^^^
#                        doit être strictement identique
```

**2. Le volume MySQL date d'un ancien déploiement avec des credentials différents**

MySQL ignore `MYSQL_USER` / `MYSQL_PASSWORD` si le volume est déjà initialisé.

Solution sans perdre les données — recréer l'utilisateur via root :

```bash
make db-grant-prod SITE=monsite
make migrate-prod SITE=monsite
```

**3. Supprimer le volume et repartir de zéro** (⚠ efface toutes les données) :

```bash
make down-prod SITE=monsite
docker volume rm monsite_database_data
make init-prod SITE=monsite
```

> Le nom du volume suit le pattern `{SITE}_database_data`.
> `docker volume ls` pour lister tous les volumes existants.

---

## Architecture Docker

| Fichier | Rôle |
|---|---|
| `docker/compose.yaml` | Base partagée : MySQL uniquement |
| `docker/compose.override.yaml` | Dev : `php -S` + Mailhog (auto-chargé) |
| `docker/compose.prod.yaml` | Prod : PHP-FPM + Nginx + container fixtures |
| `docker/php/Dockerfile` | Image dev (sans `composer install` — vendor monté) |
| `docker/php/Dockerfile.prod` | Image prod multi-stage — `composer install` baked |
| `.env.site.dist` | Template à copier pour chaque nouveau site |
| `fixtures/SITE/` | CSV chargés au `make fixtures` |
| `Makefile` | Toutes les commandes |

### Stages du Dockerfile.prod

| Stage | Description |
|---|---|
| `vendor` | `composer install` complet dans l'image `composer:latest` |
| `runtime` | PHP 8.4-fpm + extensions + OPcache + Composer + code + vendor |

---

## Nginx — exposition sur un domaine

Le container `nginx` écoute sur `HTTP_PORT` (défaut 80). Il y a deux cas selon comment tu veux exposer l'app.

---

### Cas 1 — sous-répertoire d'un domaine existant (ex: `maximefreelance.fr/back-annuaire/`)

Le Nginx principal (celui qui sert `maximefreelance.fr`) fait un reverse proxy vers le container
`nginx-maximefreelance` en utilisant son **nom Docker** — pas d'IP ni de port exposé nécessaire.

**1. Mettre les containers sur le même réseau Docker.**

Dans `docker/compose.prod.yaml`, déclarer un réseau externe partagé :

```yaml
networks:
  default:
    name: nginx-proxy
    external: true
```

Créer ce réseau une fois sur le serveur :

```bash
docker network create nginx-proxy
```

Le container du Nginx principal (celui de `maximefreelance.fr`) doit aussi être sur `nginx-proxy`.

**2. Nginx principal** — ajouter dans le `server {}` du domaine :

```nginx
location /back-annuaire/ {
    proxy_pass         http://nginx-maximefreelance/;
    proxy_set_header   Host              $host;
    proxy_set_header   X-Real-IP         $remote_addr;
    proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header   X-Forwarded-Proto $scheme;
    proxy_set_header   X-Forwarded-Prefix /back-annuaire;
}
```

> `nginx-maximefreelance` est le `container_name` défini dans `compose.prod.yaml`.
> Le `proxy_pass` avec `/` final supprime le préfixe `/back-annuaire` avant transmission.

**3. Symfony** — déclarer le proxy dans `config/packages/framework.yaml` :

```yaml
framework:
    trusted_proxies: '127.0.0.1,REMOTE_ADDR'
    trusted_headers:
        - 'x-forwarded-for'
        - 'x-forwarded-proto'
        - 'x-forwarded-prefix'
```

Symfony utilisera alors `X-Forwarded-Prefix` pour générer les URLs avec le bon préfixe
(`/back-annuaire/login`, `/back-annuaire/api/`, etc.).

---

### Cas 2 — domaine dédié (ex: `annuaire.maximefreelance.fr`)

Le container écoute directement sur le port 80.

**Dans `.env.SITE`** :

```
HTTP_PORT=80
```

**Nginx hôte** (optionnel, si tu veux HTTPS avec Certbot) :

```nginx
server {
    listen 80;
    server_name annuaire.maximefreelance.fr;

    location / {
        proxy_pass         http://127.0.0.1:80/;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }
}
```

Pas besoin de `X-Forwarded-Prefix` dans ce cas — Symfony est à la racine du domaine.
