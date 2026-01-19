#  Plateforme de Gestion et d'Optimisation des Examens

Ce projet est une solution complète de gestion des plannings d'examens, développée en **PHP** avec une architecture distribuée entre **Render** (Hébergement) et **TiDB Cloud** (Base de données).

##  Démonstration Vidéo
> **Note importante :** Vous pouvez visionner la présentation du projet et le fonctionnement de l'algorithme via le lien suivant :
>  [**Cliquez ici pour voir la vidéo de démonstration**](TON_LIEN_VIDEO_ICI)

---

## Organisation du Code Source

###  Authentification et Sécurité
* **index.php** : Point d'entrée de l'application.
* **login.php / logout.php** : Gestion des sessions et accès sécurisés.
* **auth.php** : Middleware de contrôle des permissions selon les rôles (RBAC).

### ⚙️ Cœur de l'Algorithme et Tests
* **generer_edt.php** : Algorithme principal d'optimisation gérant l'équité des professeurs, les capacités des salles et les créneaux horaires.
* **generer_conflits.php** : **Outil de Simulation** utilisé pour la **génération aléatoire** de données, permettant de tester la robustesse de l'algorithme face à des scénarios complexes.
* **conflits.php** : Interface de détection et visualisation graphique des erreurs de planning.
* **supprimer_edt.php** : Script de réinitialisation complète du planning pour de nouvelles simulations.

###  Gestion Administrative par Rôle
* **admin.php** : Administration globale et configuration du système.
* **doyen.php** : Vue décisionnelle, statistiques et validation finale du calendrier.
* **chef_dept.php** : Interface de gestion spécifique aux chefs de département.
* **enseignant.php** : **Gestion RH** permettant au Chef de Département d'afficher la liste des enseignants rattachés à son département.
* **professeur.php** : Espace personnel permettant à chaque enseignant de consulter son planning de surveillance.
* **etudiant.php** : Espace consultation de l'emploi du temps personnel pour les étudiants.

###  Données et Infrastructure
* **db.php** : Fichier de connexion PDO configuré pour l'instance distante **TiDB Cloud**.
* **fill_data.php** : Script d'injection massive pour tests de performance (40 professeurs, 1500 étudiants).
* **lieux.php** : Gestion du parc immobilier (salles, amphis) et de leurs capacités respectives.
* **planing.php** : Affichage et export graphique du calendrier final des examens.
* **database.sql** : Schéma SQL complet incluant les contraintes d'intégrité (Clés étrangères).

---

##  Identifiants de Test

| Rôle | Login | Mot de passe |
| :--- | :--- | :--- |
| **Administrateur** | `admin` | `admin123` |
| **Doyen** | `doyen` | `doyen123` |
| **Chef de Dept** | `chef1`, `chef2`, `chef3` | `chef123` |
| **Enseignant** | `prof1` à `prof40` | `prof123` |
| **Étudiant** | `etudiant1` à `etudiant1500` | `etu123` |

---

## ☁️ Architecture de Déploiement

Le projet utilise une infrastructure Cloud hybride pour garantir la haute disponibilité :

* **Serveur d'Application (Render)** : Le code source PHP est hébergé sur **Render**, permettant un déploiement continu et une exécution rapide des scripts.
* **Serveur de Données (TiDB Cloud)** : La base de données est déportée sur un cluster **NewSQL TiDB**.
    * **Justification technique** : Render ne proposant pas de stockage MySQL persistant nativement en mode gratuit, l'utilisation de TiDB Cloud garantit la persistance des données et une compatibilité 100% MySQL.
* **Interface SQL** : La maintenance des tables a été effectuée via **Chat2Query** (TiDB), remplaçant phpMyAdmin pour la gestion distante.

---

