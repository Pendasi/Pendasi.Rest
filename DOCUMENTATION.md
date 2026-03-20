# Pendasi.Rest - Framework ORM + REST API

## 🚀 Installation & Configuration

### 1. Configuration Initiale

Créez un fichier `.env` à la racine du projet:

```
APP_ENV=development
APP_DEBUG=true
DB_HOST=localhost
DB_PORT=3306
DB_NAME=pendasi
DB_USER=root
DB_PASSWORD=
JWT_SECRET=votre-clé-secrète-très-longue-ici
JWT_EXPIRY=3600
```

### 2. Initialisation de l'autoloader

```php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Pendasi\Rest\Core\ExceptionHandler;
use Pendasi\Rest\Core\Config;

// Enregistrer le handler exceptions global
ExceptionHandler::register();

// Charger la configuration
Config::load(__DIR__ . '/config.php');
```

---

## 🔐 Authentification JWT

### Générer un Token

```php
use Pendasi\Rest\Security\JWT;

$token = JWT::generate([
    'user_id' => 1,
    'email' => 'user@example.com',
    'role' => 'admin'
]);

echo json_encode(['token' => $token]);
```

### Vérifier un Token

```php
use Pendasi\Rest\Security\JWT;

$token = JWT::getTokenFromHeader();
$payload = JWT::verify($token);

if ($payload) {
    echo "Utilisateur: " . $payload['user_id'];
} else {
    echo "Token invalid or expired";
}
```

### Middleware d'authentification

```php
use Pendasi\Rest\Middleware\AuthMiddleware;

Router::get('/api/users', 'UserController@index', [AuthMiddleware::class]);
```

---

## 🛡️ Sécurité

### Protection CSRF

```php
use Pendasi\Rest\Security\Security;
use Pendasi\Rest\Middleware\CsrfMiddleware;

// Générer un token dans le formulaire
$csrfToken = Security::generateCsrfToken();

// Vérifier les requêtes POST/PUT/DELETE
Router::post('/api/users', 'UserController@store', [CsrfMiddleware::class]);
```

### Sanitization XSS

```php
use Pendasi\Rest\Security\Security;

$safeData = Security::sanitize($_POST);
```

---

## 📊 ORM Utilisation

### Définir un Model

```php
use Pendasi\Rest\Core\Database;
use Pendasi\Rest\Core\Model;

class User extends Model {
    public function __construct() {
        $db = Database::getInstance(
            getenv('DB_HOST'),
            getenv('DB_NAME'),
            getenv('DB_USER'),
            getenv('DB_PASSWORD')
        );
        
        parent::__construct($db, 'users', [
            'id', 'name', 'email', 'password', 'created_at', 'updated_at'
        ]);
    }
}
```

### Opérations CRUD

```php
$user = new User();

// Créer
$id = $user->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_BCRYPT)
]);

// Lire
$userData = $user->find($id);

// Tous les enregistrements
$allUsers = $user->all();

// Mettre à jour
$user->update($id, ['name' => 'Jane Doe']);

// Supprimer
$user->delete($id);
```

### QueryBuilder

```php
$user = new User();

// WHERE clause
$result = $user->query()
    ->where('email', 'john@example.com')
    ->first();

// ORDER BY
$users = $user->query()
    ->orderBy('created_at', 'DESC')
    ->get();

// LIMIT & OFFSET
$users = $user->query()
    ->limit(10, 0)
    ->get();

// JOINs
$posts = $user->query()
    ->join('posts', 'posts.user_id = users.id')
    ->where('users.id', 1)
    ->get();
```

### Relations

```php
// Charger un utilisateur avec ses posts
$user = new User();
$userData = $user->find(1);
$user->setAttribute('id', 1);

$posts = $user->hasMany('posts', 'user_id', 'id');

// Charger un post avec son utilisateur
$post = new Post();
$postData = $post->find(5);
$post->setAttribute('user_id', 1);

$author = $post->belongsTo('users', 'user_id', 'id');
```

---

## ✅ Validation

```php
use Pendasi\Rest\Helpers\Validator;

$data = [
    'name' => 'John',
    'email' => 'john@example.com',
    'age' => '25',
    'password' => 'secretpass',
    'password_confirmation' => 'secretpass'
];

$errors = Validator::make($data, [
    'name' => 'required|min:3|max:50',
    'email' => 'required|email',
    'age' => 'required|numeric|min:18|max:120',
    'password' => 'required|min:8|confirmed'
]);

if (Validator::hasErrors($errors)) {
    return json_encode(['errors' => $errors]);
}
```

### Règles disponibles

- `required` - Champ obligatoire
- `email` - Format email valide
- `url` - URL valide
- `numeric` - Nombre
- `integer` - Entier
- `min:N` - Longueur minimale
- `max:N` - Longueur maximale
- `regex:/pattern/` - Regex personnalisée
- `confirmed` - Doit correspondre à `fieldname_confirmation`
- `in:val1,val2` - Valeur dans la liste
- `date` - Format date valide
- `nullable` - Peut être vide

---

## 📤 Upload de Fichiers

### Upload Standard

```php
use Pendasi\Rest\Helpers\FileUploader;

$result = FileUploader::upload(
    $_FILES['file'],
    '/uploads',
    ['image/jpeg', 'image/png'],
    5242880 // 5MB
);

if ($result) {
    echo "Fichier uploadé: $result";
}
```

### Upload Chunké (gros fichiers)

```php
$result = FileUploader::uploadChunked(
    $_FILES['file'],
    '/uploads',
    $_POST['chunkIndex'],
    $_POST['totalChunks'],
    'large-file.zip',
    ['application/zip'],
    104857600 // 100MB total
);
```

---

## 🎯 Controller REST

```php
use Pendasi\Rest\Rest\Controller;

class UserController extends Controller {
    private $userModel;

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
            $this->json(['success' => false, 'message' => 'Not found']);
        }
        $this->json(['success' => true, 'data' => $user]);
    }

    public function store($data) {
        $errors = Validator::make($data, [
            'name' => 'required|min:3',
            'email' => 'required|email'
        ]);

        if (Validator::hasErrors($errors)) {
            http_response_code(422);
            $this->json(['success' => false, 'errors' => $errors]);
        }

        $id = $this->userModel->create($data);
        $this->json(['success' => true, 'id' => $id]);
    }

    public function update($id, $data) {
        $this->userModel->update($id, $data);
        $this->json(['success' => true, 'message' => 'Updated']);
    }

    public function delete($id) {
        $this->userModel->delete($id);
        $this->json(['success' => true, 'message' => 'Deleted']);
    }
}
```

---

## 🗂️ Routing

```php
use Pendasi\Rest\Rest\Router;

// Routes simples
Router::get('/api/users', 'UserController@index');
Router::post('/api/users', 'UserController@store');

// Routes avec paramètres
Router::get('/api/users/{id}', 'UserController@show');
Router::put('/api/users/{id}', 'UserController@update');
Router::delete('/api/users/{id}', 'UserController@delete');

// API RESTful automatique
Router::api('UserController');

// Dispatcher les routes
Router::dispatch();
```

---

## 🔄 Gestion des Erreurs

Toutes les erreurs sont automatiquement catchées et retournent du JSON:

```json
{
    "success": false,
    "message": "Validation error",
    "error": "Exception"
}
```

En développement (APP_ENV=development), la trace est incluse.

---

## 📝 Exemple Complet

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Pendasi\Rest\Core\Config;
use Pendasi\Rest\Core\ExceptionHandler;
use Pendasi\Rest\Rest\Router;
use Pendasi\Rest\Middleware\AuthMiddleware;

ExceptionHandler::register();
Config::load(__DIR__ . '/config.php');

// Définir les routes
Router::get('/api/users', 'UserController@index');
Router::post('/api/users', 'UserController@store');
Router::get('/api/users/{id}', 'UserController@show', [AuthMiddleware::class]);
Router::put('/api/users/{id}', 'UserController@update', [AuthMiddleware::class]);
Router::delete('/api/users/{id}', 'UserController@delete', [AuthMiddleware::class]);

// Dispatcher
Router::dispatch();
?>
```

---

## 🗄️ Migrations de Base de Données

### Créer une migration

```bash
php cli.php make:migration create_users_table
php cli.php make:migration add_email_to_users
```

### Écrire une migration

```php
<?php
namespace Pendasi\Rest\Database;

class CreateUsersTable extends Migration {
    
    public function up() {
        $this->createTable('users', function(SchemaBuilder $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 255);
            $table->unique('email');
            $table->text('bio');
            $table->boolean('active', true);
            $table->timestamps();
        });
    }

    public function down() {
        $this->dropTableIfExists('users');
    }
}
```

### Exécuter les migrations

```bash
php cli.php migrate                # Exécuter toutes les migrations en attente
php cli.php migrate:status         # Voir l'état des migrations
php cli.php rollback               # Annuler la dernière batch
```

### Types de colonnes disponibles

```php
$table->id();                      // Auto-increment INT PRIMARY KEY
$table->string('name', 255);       // VARCHAR(255)
$table->text('content');           // TEXT
$table->integer('count');          // INT
$table->decimal('price', 8, 2);    // DECIMAL(8,2)
$table->boolean('active');         // BOOLEAN
$table->date('birthday');          // DATE
$table->dateTime('published_at');  // DATETIME
$table->timestamp('created_at');   // TIMESTAMP
$table->timestamps();              // created_at + updated_at
```

### Contraintes

```php
$table->unique('email');               // UNIQUE
$table->foreignKey('user_id', 'users'); // FK -> users(id)
$table->index('email');                // INDEX
```

---

## 💾 Transactions

### Utilisation basique

```php
use Pendasi\Rest\Core\Database;

$db = Database::getInstance(...);

try {
    $db->beginTransaction();
    
    // Opérations...
    $user = new User();
    $userId = $user->create(['name' => 'John', 'email' => 'john@example.com']);
    
    $post = new Post();
    $post->create(['user_id' => $userId, 'title' => 'First post']);
    
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
    throw $e;
}
```

### Utilisation avec callback

```php
$db->transaction(function($db) {
    $user = new User();
    $userId = $user->create(['name' => 'John']);
    
    $post = new Post();
    $post->create(['user_id' => $userId, 'title' => 'First post']);
    
    // commit() automatique si pas d'erreur
    // rollback() automatique en cas d'erreur
});
```

---

## 🗑️ Soft Deletes

### Configurer un Model avec soft deletes

```php
class User extends Model {
    protected bool $useSoftDeletes = true;
    protected string $deletedAtColumn = 'deleted_at';
    
    public function __construct() {
        $db = Database::getInstance(...);
        parent::__construct($db, 'users', [...]);
    }
}
```

### Ajouter colonne dans migration

```php
$table->dateTime('deleted_at');  // Dans la migration
```

### Utiliser les soft deletes

```php
$user = new User();

// Soft delete
$user->delete(1);  // Met deleted_at à maintenant

// Récupérer (exclude les soft deleted)
$activeUsers = $user->all();  // Sans les supprimés
$user = $user->find(1);       // NULL si supprimé

// Restaurer
$user->restore(1);

// Forcer la suppression
$user->forceDelete(1);

// Voir que les soft deleted
$trashedUsers = $user->onlyTrashed()->get();

// Inclure les soft deleted
$allUsers = $user->withTrashed()->get();
```

---

## ⏰ Timestamps Automatiques

Tous les Models activent automatiquement `created_at` et `updated_at`:

```php
class User extends Model {
    protected bool $useTimestamps = true;  // Par défaut true
}
```

Colonnes automatiquement gérées:
- `created_at` - Ajoutée lors de la création
- `updated_at` - Mise à jour à chaque modification

Désactiver si nécessaire:
```php
protected bool $useTimestamps = false;
```

---

## 💾 Cache Amélioré

### Configuration

Soutient: **File**, **Redis**, **Memcached**

```php
use Pendasi\Rest\Core\CacheManager;

// File cache (défaut)
$cache = new CacheManager('file');

// Redis
$cache = new CacheManager('redis');

// Memcached
$cache = new CacheManager('memcached');
```

### Utilisation

```php
$cache = new CacheManager();

// Mettre en cache
$cache->put('user.1', $userData, 3600);

// Récupérer
$data = $cache->get('user.1');

// Vérifier
if ($cache->has('user.1')) {
    // ...
}

// Remember (récupérer ou créer)
$data = $cache->remember('user.1', function() {
    return $user->find(1);
}, 3600);

// Supprimer
$cache->delete('user.1');

// Vider tout
$cache->flush();

// Get and delete
$data = $cache->pull('user.1');
```

### Utile avec les Models

```php
$cache = new CacheManager();
$user = new User();

// Cache du find
$cachedUser = $cache->remember('user.1', function() use ($user) {
    return $user->find(1);
}, 3600);

// Cache du all
$cachedUsers = $cache->remember('users.all', function() use ($user) {
    return $user->all();
}, 600);
```

---

## 🎓 Améliorations Implémentées

✅ Migrations de base de données
✅ Transactions (BEGIN, COMMIT, ROLLBACK)
✅ Cache Redis/Memcached/File
✅ Soft deletes
✅ Timestamps automatiques (created_at, updated_at)
✅ CLI pour gérer les migrations
✅ Exception handling global
✅ JWT authentication
✅ CSRF protection
✅ Validation complète

---

## 📋 Améliorations Futures

- [ ] Eager loading (N+1 fix)
- [ ] Relations Many-to-Many
- [ ] Swagger/OpenAPI documentation
- [ ] Tests unitaires (PHPUnit)
- [ ] Database seeding
- [ ] Query optimization
- [ ] API rate limiting amélioré

---

## 🎓 Prochaines Améliorations

- [ ] Migrations de base de données
- [ ] Transactions (BEGIN, COMMIT, ROLLBACK)
- [ ] Cache Redis/Memcached
- [ ] Soft deletes
- [ ] Timestamps automatiques (created_at, updated_at)
- [ ] Eager loading (N+1 fix)
- [ ] Relations Many-to-Many
- [ ] Swagger/OpenAPI documentation
- [ ] Tests unitaires (PHPUnit)

---

## 📄 Licence

MIT

