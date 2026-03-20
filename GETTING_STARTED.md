# 🚀 Démarrer avec Pendasi.Rest

## Installation Rapide

### 1️⃣ Configuration

```bash
# Copier le fichier de configuration
cp .env.example .env

# Éditer .env avec vos paramètres
nano .env
```

### 2️⃣ Créer la Base de Données

```bash
# Créer une base vide (MySQL/MariaDB)
mysql -u root -p -e "CREATE DATABASE pendasi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3️⃣ Exécuter les Migrations

```bash
# Exécuter toutes les migrations
php cli.php migrate

# Vérifier le statut
php cli.php migrate:status
```

### 4️⃣ Démarrer le Serveur

```bash
# Si vous avez PHP 7.4+
php -S localhost:8000 -t public/

# Ou avec XAMPP
# - Placer le dossier dans htdocs/
# - Accéder à http://localhost/Pendasi.Rest/public/
```

---

## 📁 Structure du Projet

```
Pendasi.Rest/
├── public/                    # Point d'entrée
│   └── index.php
├── src/
│   ├── Core/                 # ORM, Config, Cache
│   │   ├── Database.php
│   │   ├── Model.php
│   │   ├── QueryBuilder.php
│   │   ├── Config.php
│   │   ├── Cache.php
│   │   ├── CacheManager.php
│   │   ├── ExceptionHandler.php
│   │   └── Pagination.php
│   ├── Rest/                 # Routeur, Controller
│   │   ├── Router.php
│   │   └── Controller.php
│   ├── Security/             # Authentification, Sécurité
│   │   ├── Security.php
│   │   └── JWT.php
│   ├── Helpers/              # Utilities
│   │   ├── Validator.php
│   │   ├── FileUploader.php
│   │   └── Pagination.php
│   ├── Upload/
│   │   └── FileUploader.php
│   └── Database/             # Migrations
│       ├── Migration.php
│       ├── SchemaBuilder.php
│       └── MigrationRunner.php
├── Middleware/               # Middlewares
│   ├── MiddlewareInterface.php
│   ├── AuthMiddleware.php
│   ├── RateLimitMiddleware.php
│   └── CsrfMiddleware.php
├── database/
│   └── migrations/           # Fichiers de migration
├── config.php                # Configuration
├── cli.php                   # CLI pour les migrations
├── DOCUMENTATION.md          # Documentation complète
└── .env.example              # Variables d'environnement
```

---

## 💡 Exemples d'Utilisation

### Créer un Model

```php
<?php
namespace App\Models;

use Pendasi\Rest\Core\Database;
use Pendasi\Rest\Core\Model;

class User extends Model {
    
    // Soft deletes
    protected bool $useSoftDeletes = true;
    
    // Timestamps automatiques
    protected bool $useTimestamps = true;

    public function __construct() {
        $db = Database::getInstance(
            getenv('DB_HOST'),
            getenv('DB_NAME'),
            getenv('DB_USER'),
            getenv('DB_PASSWORD')
        );
        
        parent::__construct($db, 'users', [
            'id', 'name', 'email', 'password', 'deleted_at', 'created_at', 'updated_at'
        ]);
    }
}
```

### Créer un Controller

```php
<?php
namespace App\Controllers;

use App\Models\User;
use Pendasi\Rest\Rest\Controller;
use Pendasi\Rest\Helpers\Validator;
use Pendasi\Rest\Security\Security;

class UserController extends Controller {
    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function index() {
        $users = $this->userModel->all();
        $this->json(['success' => true, 'data' => $users]);
    }

    public function show($id) {
        $user = $this->userModel->find($id);
        
        if (!$user) {
            http_response_code(404);
            $this->json(['success' => false, 'message' => 'User not found']);
        }
        
        $this->json(['success' => true, 'data' => $user]);
    }

    public function store($data) {
        // Validation
        $errors = Validator::make($data, [
            'name' => 'required|min:3|max:100',
            'email' => 'required|email',
            'password' => 'required|min:8'
        ]);

        if (Validator::hasErrors($errors)) {
            http_response_code(422);
            $this->json(['success' => false, 'errors' => $errors]);
        }

        // Sanitize
        $data = Security::sanitize($data);

        // Hasher le mot de passe
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

        // Créer l'utilisateur
        $id = $this->userModel->create($data);
        
        $this->json(['success' => true, 'id' => $id, 'message' => 'User created']);
    }

    public function update($id, $data) {
        $this->userModel->update($id, $data);
        $this->json(['success' => true, 'message' => 'User updated']);
    }

    public function delete($id) {
        $this->userModel->delete($id);  // Soft delete si activé
        $this->json(['success' => true, 'message' => 'User deleted']);
    }
}
```

### Créer les Routes

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Pendasi\Rest\Core\Config;
use Pendasi\Rest\Core\ExceptionHandler;
use Pendasi\Rest\Rest\Router;
use Pendasi\Rest\Middleware\AuthMiddleware;
use Pendasi\Rest\Middleware\CsrfMiddleware;

ExceptionHandler::register();
Config::load(__DIR__ . '/config.php');

// Routes publiques
Router::post('/api/login', 'AuthController@login');
Router::post('/api/register', 'AuthController@register');

// Routes protégées
Router::get('/api/users', 'UserController@index', [AuthMiddleware::class]);
Router::post('/api/users', 'UserController@store', [AuthMiddleware::class, CsrfMiddleware::class]);
Router::get('/api/users/{id}', 'UserController@show', [AuthMiddleware::class]);
Router::put('/api/users/{id}', 'UserController@update', [AuthMiddleware::class, CsrfMiddleware::class]);
Router::delete('/api/users/{id}', 'UserController@delete', [AuthMiddleware::class, CsrfMiddleware::class]);

// Dispatcher
Router::dispatch();
```

---

## 🛠️ Commandes CLI Disponibles

```bash
# Créer une migration
php cli.php make:migration create_posts_table
php cli.php make:migration add_status_to_users

# Exécuter les migrations
php cli.php migrate

# Voir le statut
php cli.php migrate:status

# Annuler
php cli.php rollback
```

---

## 📚 Documentation Complète

Voir [DOCUMENTATION.md](DOCUMENTATION.md) pour une documentation détaillée avec tous les exemples.

---

## 🔒 Sécurité

✅ Authentification JWT
✅ Protection CSRF
✅ Sanitization XSS
✅ Rate limiting
✅ SQL Injection prevention (prepared statements)
✅ CORS configurable

---

## 🎯 Prochaines Étapes

1. **Lire la documentation** - [DOCUMENTATION.md](DOCUMENTATION.md)
2. **Créer vos Models** - Voir section Exemples ci-dessus
3. **Créer vos Controllers** - Voir section Exemples ci-dessus
4. **Créer vos Routes** - Voir section Exemples ci-dessus
5. **Tester votre API** - Utiliser Postman ou curl

---

## 🐛 Dépannage

### Erreur de migration
```
Make sure config.php is loading correctly and database credentials are valid.
Check the migration file exists in database/migrations/
```

### Erreur JWT
```
Make sure JWT_SECRET is set in .env file
Token must be in Authorization header: "Bearer <token>"
```

### Les timestamps ne sont pas ajoutés
```
Check that useTimestamps = true in your Model
Ensure created_at and updated_at columns exist in the table
```

---

## 📞 Support

Pour toute question, consultez la documentation ou vérifiez les logs:
```bash
tail -f logs/app.log  # Si vous avez mis en place le logging
```

---

Bon développement! 🚀
