# Déploiement PME-CONFORM — serveur OVH Ubuntu

Procédure de mise en service des workers Laravel (analyses, indexation,
notifications) sur le serveur `ns3485498.ip-193-70-32.eu`.

## Pré-requis vérifiés sur la cible

| Composant | Version | État |
|---|---|---|
| Ubuntu Server | 24.04 LTS | ✅ |
| Nginx + PHP-FPM (PHP 8.2) | — | ✅ |
| PostgreSQL + pgvector | 18.4 / 0.8.2 | ✅ |
| Redis | 7.0.15 | ✅ |
| **Supervisor** | **4.2.5** | **Installé, workers à configurer** |
| Ollama | 0.30.6 (llama3.1:8b) | ✅ |
| Tesseract OCR | 5.3.4 | ✅ |

## Étape 1 — Déposer le code

```bash
# Via gestionnaire de fichiers HestiaCP, déposer le ZIP du backend dans
# /home/asconsulting/web/ns3485498.ip-193-70-32.eu/laravel/
# Puis :
cd /home/asconsulting/web/ns3485498.ip-193-70-32.eu/laravel
composer install --no-dev --optimize-autoloader
```

## Étape 2 — Configurer `.env`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ns3485498.ip-193-70-32.eu

# DB
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=asc_ia_plateforme
DB_USERNAME=asc_user
DB_PASSWORD=...

# Queue → Redis (plus performant que database, surtout avec workers concurrents)
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Ollama (local sur la même machine)
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.1:8b
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_EMBEDDINGS_ENABLED=true

# Sessions / cache
SESSION_DRIVER=redis
CACHE_STORE=redis
```

## Étape 3 — Migrations + cache

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Étape 4 — Activer les workers via Supervisor

```bash
# Copier la config depuis le repo
sudo cp deploy/supervisor/pme-conform-worker.conf /etc/supervisor/conf.d/

# Recharger Supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Démarrer les 3 groupes de workers
sudo supervisorctl start pme-conform-analyses:*
sudo supervisorctl start pme-conform-default:*
sudo supervisorctl start pme-conform-referentiels:*

# Vérifier
sudo supervisorctl status
```

Sortie attendue (exemple) :
```
pme-conform-analyses:pme-conform-analyses_00    RUNNING   pid 12345, uptime 0:00:05
pme-conform-analyses:pme-conform-analyses_01    RUNNING   pid 12346, uptime 0:00:05
pme-conform-default:pme-conform-default_00      RUNNING   pid 12347, uptime 0:00:05
pme-conform-default:pme-conform-default_01      RUNNING   pid 12348, uptime 0:00:05
pme-conform-referentiels:pme-conform-...        RUNNING   pid 12349, uptime 0:00:05
```

## Configuration des workers

| Queue | numprocs | Timeout | Cible |
|---|---|---|---|
| `analyses` | **2** | 4h | Analyse enrichie IA (Ollama llama3.1:8b CPU, longue) |
| `default` | **2** | 5min | Notifications, exports XLSX, jobs courts |
| `referentiels` | **1** | 1h | Indexation d'un référentiel (chunks + embeddings + classification thématique LLM) |

Total : **5 process workers en parallèle**. Avec 62 Go de RAM et la limite memory=2048MB par worker, ça laisse largement la place à Ollama + Nginx + PostgreSQL.

## Étape 5 — Front-end React

```bash
# Sur votre PC, à la racine de frontend/
npm install
npm run build
# Copier le contenu de dist/ dans laravel/public/
scp -r dist/* asconsulting@ns3485498.ip-193-70-32.eu:/home/asconsulting/web/ns3485498.ip-193-70-32.eu/laravel/public/
```

## Étape 6 — Configurer Nginx (HestiaCP)

Dans HestiaCP : éditer le domaine, pointer le root vers `laravel/public/`.

Template Nginx Laravel standard (devrait être détecté auto par HestiaCP). Vérifier que `try_files $uri $uri/ /index.php?$query_string;` est présent.

## Commandes de maintenance

```bash
# Après chaque déploiement de code, recharger les workers pour qu'ils
# voient le nouveau code (sinon ils gardent l'ancien en mémoire) :
php artisan queue:restart

# Voir les logs d'un worker en temps réel
sudo tail -f /var/log/supervisor/pme-conform-analyses.log

# Arrêter / relancer un worker
sudo supervisorctl stop pme-conform-analyses:*
sudo supervisorctl start pme-conform-analyses:*

# Compter les jobs en file (via Redis)
redis-cli LLEN queues:analyses
redis-cli LLEN queues:default
redis-cli LLEN queues:referentiels

# Voir les jobs en échec
php artisan queue:failed
php artisan queue:retry all
```

## Pourquoi 2 workers `analyses` ?

Une analyse enrichie peut tourner 30 min à 3 h sur Ollama CPU. Avec 2 workers
en parallèle, on peut traiter **2 analyses simultanément** (utile dès qu'on a
plusieurs clients actifs). La RAM 62 Go absorbe sans problème :
- llama3.1:8b chargé ~9 Go
- 2 prompts en parallèle = ~2 Go supplémentaires
- 2 process PHP worker = ~4 Go
- → reste 47 Go pour PostgreSQL + Nginx + Redis + autres

## Évolution future

Si vous ajoutez plus de clients et que les 2 workers ne suffisent plus :
- Passez `numprocs=4` dans la config Supervisor → 4 analyses en parallèle
- Au-delà : ajoutez une instance Public Cloud GPU L4 OVH pour Ollama
  (cf. note GPU en fin de fiche serveur) et pointez `OLLAMA_BASE_URL`
  vers son IP. Ça divise les temps d'analyse par 5-10x.
