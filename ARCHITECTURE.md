# O-CMS — Architecture

## Principles

1. **Zero Database** — All data stored as JSON files in `data/`
2. **Simplicity** — Readable code, predictable structure
3. **Elegance** — High-quality admin and frontend UI/UX
4. **Security** — Input sanitization, CSRF protection, password hashing
5. **Portability** — Copy the folder to migrate your site

## Directory Structure

```
o-cms/
├── index.php              # Frontend entry point
├── install.php            # Installation wizard (delete after setup)
├── .htaccess              # URL rewriting and data protection
├── core/                  # CMS engine
│   ├── App.php            # Application bootstrap and routing (~4900 lines)
│   ├── Router.php         # URL pattern matching
│   ├── JsonStorage.php    # JSON file read/write with atomic operations
│   ├── Auth.php           # Authentication, roles, and API tokens
│   ├── Session.php        # Secure session management
│   ├── Helpers.php        # Utility functions (email, CSRF, images, analytics)
│   ├── Hooks.php          # Event/observer system for extensions
│   ├── ExtensionManager.php # Extension lifecycle management
│   ├── SearchEngine.php   # Full-text search with fuzzy matching
│   └── LayoutRenderer.php # Visual layout builder rendering
├── data/                  # ALL content data (JSON flat-files)
│   ├── config.json        # Global configuration
│   ├── pages/             # One page = one .json file
│   ├── articles/          # One article = one .json file
│   ├── categories/        # Article categories
│   ├── tags/              # Article tags
│   ├── menus/             # Navigation menus
│   ├── users/             # One user = one .json file
│   ├── media/             # Media metadata
│   ├── forms/             # Form definitions and submissions
│   ├── comments/          # Article comments
│   ├── galleries/         # Image galleries
│   ├── lessons/           # Educational content
│   ├── quizzes/           # Interactive quizzes
│   ├── quiz-results/      # Quiz submissions
│   ├── layouts/           # Layout builder configurations
│   ├── analytics/         # Daily visitor statistics
│   ├── revisions/         # Content revision history
│   ├── widgets/           # Widget configurations
│   ├── translations/      # Translation files
│   ├── snippets/          # Code injection snippets
│   ├── logs/              # Activity logs
│   ├── cache/             # HTML cache
│   └── backups/           # Automated backups
├── themes/                # Installed themes
│   └── flavor/            # Default "Flavor" theme
│       ├── theme.json     # Theme metadata and color palette
│       ├── assets/        # CSS, JS, images
│       ├── partials/      # Reusable template parts (header, footer)
│       └── templates/     # Page templates (home, page, article, blog, 404, search)
├── extensions/            # Installed extensions/plugins
├── admin/                 # Administration panel
│   ├── index.php          # Admin entry point
│   └── views/             # Admin page templates
└── uploads/               # User-uploaded files
    ├── images/
    ├── documents/
    └── media/
```

## JSON Data Conventions

### Page (`data/pages/{slug}.json`)
```json
{
  "id": "uuid-v4",
  "title": "Page Title",
  "slug": "page-title",
  "content": "<p>HTML content</p>",
  "template": "page",
  "layout": "none",
  "status": "published",
  "meta": {
    "title": "SEO Title",
    "description": "SEO Description",
    "og_image": ""
  },
  "order": 0,
  "parent": null,
  "author": "admin",
  "created_at": "2026-04-13T10:00:00+00:00",
  "updated_at": "2026-04-13T10:00:00+00:00",
  "views": 0
}
```

### Article (`data/articles/{slug}.json`)
```json
{
  "id": "uuid-v4",
  "title": "Article Title",
  "slug": "article-title",
  "excerpt": "Brief description",
  "content": "<p>HTML content</p>",
  "cover_image": "/uploads/images/cover.webp",
  "category": "category-slug",
  "tags": ["tag1", "tag2"],
  "status": "published",
  "meta": { "title": "", "description": "", "og_image": "" },
  "author": "admin",
  "created_at": "2026-04-13T10:00:00+00:00",
  "updated_at": "2026-04-13T10:00:00+00:00",
  "views": 0
}
```

### User (`data/users/{username}.json`)
```json
{
  "id": "uuid-v4",
  "username": "admin",
  "email": "admin@example.com",
  "password": "$2y$10$...",
  "display_name": "Administrator",
  "role": "super_administrator",
  "avatar": "",
  "active": true,
  "api_tokens": [],
  "created_at": "2026-04-13T10:00:00+00:00",
  "last_login": null
}
```

### Menu (`data/menus/{name}.json`)
```json
{
  "name": "main",
  "label": "Main Menu",
  "items": [
    {
      "id": "unique-id",
      "label": "Home",
      "url": "/",
      "target": "_self",
      "published": true,
      "children": []
    }
  ]
}
```

## Request Flow

```
1. Request → .htaccess rewrites to index.php (or admin/index.php)
2. index.php → loads core/App.php, creates singleton
3. App::run() → registers routes, boots extensions, dispatches
4. Router::dispatch() → matches URL pattern, executes handler
5. Handler → reads/writes JSON via JsonStorage
6. Response → renders template from active theme
```

## Role Hierarchy

| Level | Role | Capabilities |
|-------|------|-------------|
| 5 | `super_administrator` | Full access, settings, users, extensions |
| 4 | `administrator` | Content management, analytics, backups |
| 3 | `editor` | Pages, articles, categories, menus, media |
| 2 | `publisher` | Create and publish own articles |
| 1 | `registered` | Profile management, comments |

## Security

- Passwords hashed with `password_hash()` (bcrypt)
- CSRF tokens on every form
- Input sanitization with `htmlspecialchars()`
- JSON data in `data/` protected by `.htaccess` (Deny from all)
- Session ID regeneration after login
- Rate limiting on login, registration, and comment forms
- File upload sanitization with slug + random string

## Extension System

Extensions live in `extensions/{id}/` and require an `extension.json` manifest.

### Extension Structure
```
extensions/my-extension/
├── extension.json    # Manifest (required)
├── boot.php          # Entry point (loaded on every request when enabled)
├── install.php       # Runs on installation
├── uninstall.php     # Runs on uninstallation
├── views/            # Admin views
│   └── index.php
├── templates/        # Frontend templates (optional)
├── assets/           # CSS/JS (optional)
└── data/             # Extension-specific JSON data (optional)
```

### Manifest (`extension.json`)
```json
{
  "id": "my-extension",
  "name": "My Extension",
  "description": "What it does",
  "version": "1.0.0",
  "author": "Author Name",
  "license": "MIT",
  "entry_point": "boot.php",
  "enabled": false,
  "has_admin": true,
  "has_frontend": false,
  "admin_menu": {
    "label": "My Extension",
    "icon": "puzzle",
    "position": "extensions"
  },
  "permissions": [],
  "hooks": []
}
```

### Boot Lifecycle
1. `App::run()` / `App::runAdmin()` calls `ExtensionManager::bootAll()`
2. For each extension with `enabled: true`, `boot.php` is executed
3. Inside boot.php, `$app` (App instance) and `$extension` (manifest) are available
4. Extensions can register routes, hooks, admin menu items, and use the full core API

### Hook System
```php
// Register a hook in boot.php
Hooks::on('article.before_save', function($article) {
    // Modify article before saving
    return $article;
}, priority: 10);

// Built-in events:
// app.before_dispatch, admin.before_dispatch, extension.booted
```

## REST API

All API endpoints require a Bearer token in the Authorization header.
Tokens are managed in the admin panel under API.

```
GET  /api/articles          # List published articles
GET  /api/articles/{slug}   # Single article
POST /api/articles          # Create article (author+)
POST /api/articles/{slug}   # Update article (author+)
GET  /api/pages             # List published pages
GET  /api/pages/{slug}      # Single page
GET  /api/categories        # List categories
GET  /api/menus/{name}      # Menu by name
GET  /api/media             # List media files
GET  /api/settings          # Site configuration (super_admin)
GET  /api/analytics?days=30 # Visit statistics (admin+)
```
