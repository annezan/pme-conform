# Commandes serveur — PME-CONFORM

Mémo des commandes à lancer sur le serveur **ns3485498.ip-193-70-32.eu** (OVH / HestiaCP).
Pour chaque bloc : **quand** le lancer et **pourquoi**.

Chemins de référence :
- Racine du dépôt : `/home/asconsulting/web/ns3485498.ip-193-70-32.eu/pme-conform`
- Racine Laravel : `.../pme-conform/backend`

Le cycle complet a **deux endroits** :
- **A. Sur TON PC** (dossier `C:\Users\pcsof\Documents\DCP\ProjetDCP`) → on modifie le code et on le publie sur GitHub.
- **B. Sur le SERVEUR** (via PuTTY) → on récupère le code depuis GitHub et on l'active (sections 0 à 6).

---

## A. CÔTÉ PC — PRÉPARER ET PUBLIER LE CODE SUR GITHUB

**Quand :** dès que tu as modifié le code (ou que je l'ai modifié pour toi) et que tu veux l'envoyer vers GitHub, avant de déployer sur le serveur.
**Où :** dans un terminal sur ton PC, PAS dans PuTTY.

```powershell
cd C:\Users\pcsof\Documents\DCP\ProjetDCP

# ⚠️ UNIQUEMENT si tu as modifié le FRONTEND (React, dossier frontend/) :
#    reconstruire le build (le resultat frontend/dist est versionne dans git).
cd frontend
npm run build
cd ..

# Publier sur GitHub :
git add -A
git commit -m "Description claire de la modification"
git push origin main
```

- `npm run build` → régénère `frontend/dist` (le site compilé). **À ne faire que si le front a changé** ; inutile pour une modif backend seule.
- `git add -A` → prépare tous les fichiers modifiés (le `.env` reste exclu automatiquement).
- `git commit` → enregistre la modification avec un message.
- `git push origin main` → envoie sur GitHub. **C'est ce que le serveur récupérera** avec `git pull` (section B.1).

👉 Une fois le `push` fait, passe au serveur : sections **0** (utilisateurs) puis **1** (déployer).

---

## B. CÔTÉ SERVEUR (les sections ci-dessous se lancent dans PuTTY)

## 0. À COMPRENDRE AVANT TOUT : les deux utilisateurs

Le serveur a **deux comptes** avec des rôles différents. Regarde toujours le début de l'invite :

| Invite | Utilisateur | Sert pour | Comment y aller |
|---|---|---|---|
| `...:~#` (dièse) | **root** | Système : `apt`, Supervisor, Nginx, PostgreSQL, redémarrer PHP | se connecter en `ubuntu` puis `sudo -i` |
| `...:~$` (dollar) | **asconsulting** | L'application : `git`, `composer`, `php artisan` | c'est le compte de l'app |

⚠️ `asconsulting` **ne peut pas** faire `sudo` (c'est normal). Pour une commande système, il faut être **root** (via `ubuntu` → `sudo -i`).
Astuce : depuis root, on peut lancer une commande en tant qu'asconsulting sans changer de fenêtre :
```bash
su - asconsulting -c "LA_COMMANDE_ICI"
```

---

## 1. DÉPLOYER UNE MISE À JOUR DU CODE ⭐ (le cas le plus fréquent)

**Quand :** à chaque fois que du nouveau code a été poussé sur GitHub (correctifs, fonctionnalités).
**Pourquoi :** récupérer le code, régénérer les caches, et forcer PHP + les workers à charger la nouvelle version.

**① En asconsulting — récupérer le code et reconstruire :**
```bash
su - asconsulting -c "cd /home/asconsulting/web/ns3485498.ip-193-70-32.eu/pme-conform && git pull && cp -r frontend/dist/* backend/public/ && cd backend && php artisan config:cache && php artisan route:cache && php artisan queue:restart"
```
- `git pull` → télécharge le nouveau code depuis GitHub.
- `cp -r frontend/dist/* backend/public/` → met à jour le site React (le build est versionné dans git).
- `config:cache` / `route:cache` → régénèrent les caches de config et de routes (sinon l'ancienne config/routes reste active).
- `queue:restart` → demande aux workers de se relancer pour prendre le nouveau code.

**② En root — redémarrer PHP (INDISPENSABLE) :**
```bash
systemctl restart php8.3-fpm
```
- **Pourquoi c'est obligatoire :** le serveur a `opcache.validate_timestamps=0`. PHP garde l'ancien code compilé en mémoire tant qu'on ne le redémarre pas. **Sans ce redémarrage, tes changements de code back ne seront PAS visibles** (c'est ce qui a causé une erreur 500 lors du premier déploiement).

---

## 2. CAS PARTICULIERS (en plus de la section 1)

### 2.a — Une dépendance PHP a été ajoutée (le fichier `composer.json` a changé)
**Quand :** le commit modifie `composer.json` / `composer.lock`.
**Pourquoi :** installer les nouvelles librairies PHP.
```bash
su - asconsulting -c "cd /home/asconsulting/web/ns3485498.ip-193-70-32.eu/pme-conform/backend && composer install --no-dev --optimize-autoloader"
```

### 2.b — Une nouvelle migration de base de données a été ajoutée
**Quand :** le commit ajoute un fichier dans `backend/database/migrations/`.
**Pourquoi :** créer les nouvelles tables/colonnes.
```bash
su - asconsulting -c "cd /home/asconsulting/web/ns3485498.ip-193-70-32.eu/pme-conform/backend && php artisan migrate --force"
```
✅ Sans danger : `migrate` n'ajoute que ce qui manque, ne touche pas aux données.

### 2.c — Un nouveau pré-requis système (ex. Ghostscript pour l'OCR)
**Quand :** une fonctionnalité a besoin d'un nouvel outil serveur.
**Pourquoi :** l'installer une fois pour toutes.
```bash
apt install -y ghostscript          # en root — pour l'OCR des PDF scannés
```

### 2.d — La configuration des workers a changé (`deploy/supervisor/pme-conform-worker.conf`)
**Quand :** le commit modifie le fichier `.conf` des workers.
**Pourquoi :** appliquer la nouvelle config Supervisor.
```bash
# en root :
cp /home/asconsulting/web/ns3485498.ip-193-70-32.eu/pme-conform/backend/deploy/supervisor/pme-conform-worker.conf /etc/supervisor/conf.d/
supervisorctl reread
supervisorctl update
supervisorctl status
```

---

## 3. GÉRER LES WORKERS (Supervisor) — en root

**Quand :** vérifier ou piloter les traitements en arrière-plan (analyses, indexation, OCR…).

```bash
supervisorctl status                          # voir l'etat des 7 workers (doivent etre RUNNING)
supervisorctl restart pme-conform-analyses:*  # relancer un groupe precis
tail -f /var/log/supervisor/pme-conform-analyses.log   # suivre les logs en direct
```
Rappel : après un déploiement de code, `queue:restart` (section 1) suffit — pas besoin de toucher à Supervisor sauf si le `.conf` a changé (section 2.d).

---

## 4. RÉINDEXER LES DOCUMENTS — en asconsulting

**Quand :** des documents sont en statut « erreur », ou après avoir amélioré l'extraction (ex. OCR).
**Pourquoi :** rejouer l'extraction (OCR) + embeddings pour rendre les documents exploitables par l'IA.

```bash
cd /home/asconsulting/web/ns3485498.ip-193-70-32.eu/pme-conform/backend

php artisan document:diagnostiquer --list        # liste les documents en erreur (avec leur ID)
php artisan document:diagnostiquer 42             # diagnostique le document 42 (affiche la cause exacte)
php artisan document:diagnostiquer 42 --reprocess # relance l'indexation complete du document 42
```

---

## 5. VÉRIFICATIONS / DIAGNOSTIC

**Quand :** vérifier que tout tourne, ou enquêter sur un problème.

```bash
# --- en root ---
v-list-sys-services                 # etat des services (nginx, php-fpm, postgresql...)
systemctl status ollama --no-pager  # etat d'Ollama (IA)
redis-cli ping                      # doit repondre PONG

# --- en asconsulting (ou root) : pgvector actif dans la base ? ---
PGPASSWORD='LE_MOT_DE_PASSE' psql -h 127.0.0.1 -U asc_user -d asc_ia_plateforme -c "SELECT extname, extversion FROM pg_extension WHERE extname='vector';"

# --- Ollama : modeles installes ---
ollama list

# --- logs applicatifs Laravel ---
tail -n 50 /home/asconsulting/web/ns3485498.ip-193-70-32.eu/pme-conform/backend/storage/logs/laravel.log
```

---

## 6. ⛔ À NE JAMAIS FAIRE (efface les données !)

Ces commandes **détruisent la base de données**. Ne JAMAIS les lancer en production :

```bash
php artisan migrate:fresh      # ⛔ efface TOUTES les tables
php artisan migrate:refresh    # ⛔ efface et rejoue tout
php artisan db:wipe            # ⛔ vide la base
```
Pour les migrations, utiliser **uniquement** `php artisan migrate --force` (section 2.b), qui est additif et sûr.
