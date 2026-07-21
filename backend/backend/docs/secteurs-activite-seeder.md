# Documentation - Seeder Secteurs d'Activité

## 📋 Overview

Le seeder `SecteursActiviteSeeder` permet de peupler automatiquement la table `secteurs_activite` avec les secteurs d'activité par défaut définis dans `RefDataController::SECTEURS_DEFAUT`.

## 🚀 Utilisation

### Méthode 1 : Commande Artisan (Recommandée)

```bash
# Peupler si la table est vide
php artisan secteurs:seed

# Forcer le repeuplement (supprime les données existantes)
php artisan secteurs:seed --force
```

### Méthode 2 : Seeder direct

```bash
# Exécuter le seeder directement
php artisan db:seed --class=SecteursActiviteSeeder
```

## 📊 Secteurs inclus

Le seeder crée automatiquement 18 secteurs d'activité :

| Nom | Code Généré | Description |
|-----|-------------|-------------|
| Administration publique | ADMINISTRATION_ | Services gouvernementaux, administrations publiques, collectivités territoriales |
| Agroalimentaire | AGROALIMENTAIRE | Production alimentaire, transformation agricole, industries agroalimentaires |
| Assurance | ASSURANCE | Compagnies d'assurance, courtiers, intermédiaires d'assurance |
| Banque & Finance | BANQUE_FINANCE | Banques, établissements financiers, services financiers, fintech |
| BTP & Immobilier | BTP_IMMOBILIER | Bâtiment et travaux publics, promotion immobilière, construction |
| Commerce & Distribution | COMMERCE_DISTRI | Commerce de détail, commerce de gros, distribution, e-commerce |
| Education & Formation | EDUCATION_FORMA | Etablissements d'enseignement, centres de formation, éducation en ligne |
| Energie | ENERGIE | Production et distribution d'énergie, renouvelable, pétrole et gaz |
| Hotellerie & Restauration | HOTELLERIE_REST | Hôtels, restaurants, cafés, services de restauration |
| Industrie | INDUSTRIE | Industrie manufacturière, production, usines, transformation |
| Logistique & Transport | LOGISTIQUE_TRAN | Transport de marchandises et de passagers, logistique, entreposage |
| Media & Communication | MEDIA_COMMUNICA | Presse, radio, télévision, agences de communication, publicité |
| Mines | MINES | Exploitation minière, extraction de ressources naturelles |
| ONG & Associations | ONG_ASSOCIATION | Organisations non gouvernementales, associations à but non lucratif |
| Santé | SANTE | Etablissements de santé, cabinets médicaux, pharmaceutiques |
| Services aux entreprises | SERVICES_AUX_EN | Conseil, audit, services aux entreprises, support administratif |
| Telecom | TELECOM | Opérateurs télécoms, fournisseurs d'accès internet, services de communication |
| Tourisme | TOURISME | Agences de voyages, tourisme, loisirs, activités touristiques |

## 🔧 Fonctionnalités

### Génération automatique des codes

Le seeder génère automatiquement des codes à partir des noms de secteurs :
- Conversion en majuscules
- Remplacement des caractères spéciaux (`&`, espaces, accents)
- Limitation à 15 caractères maximum
- Nettoyage des underscores multiples

### Descriptions prédéfinies

Chaque secteur bénéficie d'une description détaillée et pertinente pour faciliter la compréhension et l'utilisation.

### Sécurité des données

Le seeder inclut des protections :
- Vérification si la table contient déjà des données
- Option `--force` pour forcer le repeuplement
- Messages informatifs clairs

## 📁 Fichiers concernés

- **Seeder principal** : `database/seeders/SecteursActiviteSeeder.php`
- **Commande Artisan** : `app/Console/Commands/SeedSecteursActivite.php`
- **Source des données** : `app/Http/Controllers/Api/RefDataController.php`
- **Modèle** : `app/Models/SecteurActivite.php`

## 🔄 Intégration avec l'API

Les secteurs créés par ce seeder sont automatiquement disponibles via :
- `GET /api/secteurs-activite-liste` (sans authentification)
- `GET /api/secteurs-activite` (avec authentification)
- Interface Swagger UI : `http://127.0.0.1:8000/api/documentation`

## 🛠️ Maintenance

### Ajouter un nouveau secteur

1. Modifier `RefDataController::SECTEURS_DEFAUT`
2. Ajouter une description dans le seeder si nécessaire
3. Exécuter `php artisan secteurs:seed --force`

### Mettre à jour les descriptions

Les descriptions sont gérées dans la méthode `genererDescription()` du seeder.

## 🎯 Bonnes pratiques

- **Utiliser la commande Artisan** pour une meilleure expérience utilisateur
- **Vérifier les données** après exécution avec `php artisan tinker`
- **Utiliser `--force`** uniquement en environnement de développement
- **Maintenir la cohérence** entre `SECTEURS_DEFAUT` et les descriptions

## 📝 Exemples d'utilisation

```bash
# Vérifier l'état actuel
php artisan tinker
>>> App\Models\SecteurActivite::count();

# Peupler les secteurs
php artisan secteurs:seed;

# Vérifier le résultat
php artisan tinker
>>> App\Models\SecteurActivite::select('nom', 'code')->get();
```
