<?php
/**
 * O-CMS — Application Bootstrap
 *
 * Main engine of O-CMS, a flat-file CMS powered by JSON storage.
 * Handles routing, authentication, content management, media uploads,
 * AI content generation, analytics, and all frontend/admin operations.
 *
 * @package O-CMS
 * @version 1.0.0
 */

/**
 * Core application class (singleton).
 *
 * Wires together Router, JsonStorage, Auth, and ExtensionManager,
 * then registers every frontend and admin route.
 */
class App {
    public Router $router;
    public JsonStorage $storage;
    public Auth $auth;
    public ExtensionManager $extensions;
    public array $config;

    private static ?App $instance = null;

    /**
     * Return the singleton App instance.
     *
     * @return App
     */
    public static function getInstance(): App {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (singleton pattern).
     * Loads all core modules and initializes components.
     */
    private function __construct() {
        // Load helpers
        require_once __DIR__ . '/Helpers.php';
        require_once __DIR__ . '/JsonStorage.php';
        require_once __DIR__ . '/Session.php';
        require_once __DIR__ . '/Auth.php';
        require_once __DIR__ . '/Router.php';
        require_once __DIR__ . '/Hooks.php';
        require_once __DIR__ . '/ExtensionManager.php';
        require_once __DIR__ . '/LayoutRenderer.php';
        require_once __DIR__ . '/SearchEngine.php';

        // Initialize core components
        $this->storage = new JsonStorage();
        $this->config = $this->storage->readFile('config.json') ?? [];

        Session::start();

        $this->auth = new Auth($this->storage);

        // Extension Manager
        $this->extensions = new ExtensionManager($this);

        // Compute base path from the current URL
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $this->router = new Router($basePath);
    }

    /**
     * Boot and dispatch the frontend application.
     *
     * @return void
     */
    public function run(): void {
        $this->setupFrontendRoutes();
        $this->extensions->bootAll();
        Hooks::trigger('app.before_dispatch', $this);
        $this->router->dispatch();
    }

    /**
     * Boot and dispatch the admin application.
     *
     * @return void
     */
    public function runAdmin(): void {
        $this->setupAdminRoutes();
        $this->extensions->bootAll();

        // Phone home: one-time anonymous installation notification
        if (!empty($this->config['phone_home_allowed']) && empty($this->config['installation_notified'])) {
            $this->sendInstallNotification();
        }

        Hooks::trigger('admin.before_dispatch', $this);
        $this->router->dispatch();
    }

    // ─── FRONTEND ROUTES ───

    /**
     * Register all frontend (public-facing) routes.
     *
     * @return void
     */
    private function setupFrontendRoutes(): void {
        $app = $this;

        // Homepage
        $this->router->get('/', function () use ($app) {
            ocms_track_visit('/', 'page');
            $page = $app->storage->find('pages', 'home');

            // Featured articles for the homepage
            $featured = $app->storage->findAll('articles', function ($a) {
                if ($a['status'] !== 'published') return false;
                if (!empty($a['publish_at']) && $a['publish_at'] > date('c')) return false;
                return !empty($a['featured']);
            }, 'updated_at', 'desc');

            if ($page && !empty($page['layout']) && $page['layout'] !== 'none') {
                $app->renderWithLayout($page);
            } else {
                $app->render('home', ['page' => $page, 'featured' => $featured]);
            }
        });

        // Blog listing (registered BEFORE /{slug} to avoid route conflict)
        $this->router->get('/blog', function () use ($app) {
            ocms_track_visit('/blog', 'page');

            $sort = $_GET['sort'] ?? 'date';
            $sortField = $sort === 'alpha' ? 'title' : 'created_at';
            $sortDir = $sort === 'alpha' ? 'asc' : 'desc';
            $filterCat = $_GET['cat'] ?? '';

            $articles = $app->storage->findAll('articles', function ($a) use ($filterCat) {
                if ($a['status'] !== 'published') return false;
                if (!empty($a['publish_at']) && $a['publish_at'] > date('c')) return false;
                if ($filterCat && ($a['category'] ?? '') !== $filterCat) return false;
                return true;
            }, $sortField, $sortDir);

            $categories = $app->storage->findAll('categories', null, 'name', 'asc');
            $app->render('blog', ['articles' => $articles, 'categories' => $categories, 'sort' => $sort, 'filterCat' => $filterCat]);
        });

        // Single article
        $this->router->get('/blog/{slug}', function ($params) use ($app) {
            $article = $app->storage->find('articles', $params['slug']);
            if (!$article || $article['status'] !== 'published') {
                http_response_code(404);
                $app->render('404');
                return;
            }
            // Scheduled publishing: do not show future articles
            if (!empty($article['publish_at']) && $article['publish_at'] > date('c')) {
                http_response_code(404);
                $app->render('404');
                return;
            }
            ocms_track_visit('/blog/' . $params['slug'], 'article');

            // Increment article view counter
            $article['views'] = ($article['views'] ?? 0) + 1;
            $app->storage->save('articles', $article['slug'], $article);

            // Load approved comments
            $comments = $app->storage->findAll('comments', function ($c) use ($params) {
                return ($c['article_slug'] ?? '') === $params['slug'] && ($c['status'] ?? '') === 'approved';
            }, 'created_at', 'asc');

            // All published articles for prev/next navigation and related
            $allArticles = $app->storage->findAll('articles', function ($a) {
                if ($a['status'] !== 'published') return false;
                if (!empty($a['publish_at']) && $a['publish_at'] > date('c')) return false;
                return true;
            }, 'created_at', 'desc');

            // Prev/Next
            $prevArticle = null;
            $nextArticle = null;
            $found = false;
            foreach ($allArticles as $a) {
                if ($found) { $nextArticle = $a; break; }
                if ($a['slug'] === $article['slug']) { $found = true; continue; }
                $prevArticle = $a;
            }

            // Related articles (same category or shared tags, max 3)
            $related = [];
            $artCategory = $article['category'] ?? '';
            $artTags = $article['tags'] ?? [];
            foreach ($allArticles as $a) {
                if ($a['slug'] === $article['slug']) continue;
                if (count($related) >= 3) break;
                $sameCat = $artCategory && ($a['category'] ?? '') === $artCategory;
                $commonTags = array_intersect($artTags, $a['tags'] ?? []);
                if ($sameCat || !empty($commonTags)) {
                    $related[] = $a;
                }
            }

            $app->render('article', [
                'article' => $article,
                'comments' => $comments,
                'prevArticle' => $prevArticle,
                'nextArticle' => $nextArticle,
                'related' => $related,
            ]);
        });

        // Submit comment
        $this->router->post('/blog/{slug}/comment', function ($params) use ($app) {
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/blog/' . $params['slug']);
                return;
            }
            if (!ocms_rate_limit('comment', 3, 60)) {
                ocms_flash_set('error', 'Stai commentando troppo velocemente. Riprova tra un minuto.');
                ocms_redirect(ocms_base_url() . '/blog/' . $params['slug'] . '#comments');
                return;
            }

            $article = $app->storage->find('articles', $params['slug']);
            if (!$article || $article['status'] !== 'published') {
                http_response_code(404);
                return;
            }

            // Captcha
            if (!ocms_captcha_verify($_POST['captcha'] ?? '')) {
                ocms_flash_set('error', 'Risposta captcha errata');
                ocms_redirect(ocms_base_url() . '/blog/' . $params['slug'] . '#comments');
                return;
            }

            $authorName = trim($_POST['author_name'] ?? '');
            $authorEmail = trim($_POST['author_email'] ?? '');
            $body = trim($_POST['comment_body'] ?? '');
            $parentId = trim($_POST['parent_id'] ?? '');

            if (!$authorName || !$body) {
                ocms_flash_set('error', 'Nome e commento sono obbligatori');
                ocms_redirect(ocms_base_url() . '/blog/' . $params['slug'] . '#comments');
                return;
            }

            $commentId = 'c-' . time() . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
            $comment = [
                'id' => $commentId,
                'article_slug' => $params['slug'],
                'author_name' => $authorName,
                'author_email' => $authorEmail,
                'body' => strip_tags($body),
                'parent_id' => $parentId ?: null,
                'status' => 'pending',
                'created_at' => ocms_now(),
                'ip_hash' => hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($app->config['site_name'] ?? 'ocms')),
            ];

            $app->storage->save('comments', $commentId, $comment);

            // Email notification to the article author (if configured)
            $config = ocms_config();
            $notifyEmail = $config['smtp']['from_email'] ?? '';
            if ($notifyEmail) {
                $siteName = $config['site_name'] ?? 'O-CMS';
                $articleTitle = $article['title'];
                $safeAuthor = htmlspecialchars($authorName);
                $safeBody = htmlspecialchars($body);
                $htmlBody = "<p>Nuovo commento su <strong>{$articleTitle}</strong></p><p>Da: {$safeAuthor}</p><blockquote>{$safeBody}</blockquote>";
                @ocms_send_mail($notifyEmail, "Nuovo commento: {$articleTitle} — {$siteName}", $htmlBody);
            }

            ocms_flash_set('success', 'Commento inviato! Sarà visibile dopo l\'approvazione.');
            ocms_redirect(ocms_base_url() . '/blog/' . $params['slug'] . '#comments');
        });

        // Frontend search
        $this->router->get('/search', function () use ($app) {
            $q = trim($_GET['q'] ?? '');
            $engine = new SearchEngine($app);
            $result = $q ? $engine->search($q, [
                'types' => ['page', 'article', 'lesson'],
                'status' => 'published',
                'limit' => 20,
            ]) : ['results' => [], 'total' => 0, 'query' => ''];
            $app->render('search', ['query' => $q, 'result' => $result]);
        });

        // Form submission (frontend)
        $this->router->post('/form/submit/{slug}', function ($params) use ($app) {
            // CSRF protection
            if (!ocms_csrf_verify()) {
                http_response_code(403);
                echo 'Token di sicurezza non valido';
                return;
            }
            $form = $app->storage->find('forms', $params['slug']);
            if (!$form) { http_response_code(404); echo 'Form non trovato'; return; }

            // Verify captcha
            $captchaKey = 'form_captcha_' . $params['slug'];
            $captchaExpected = $_SESSION[$captchaKey] ?? '';
            $captchaInput = trim($_POST['_captcha'] ?? '');
            if ($captchaExpected && $captchaInput !== $captchaExpected) {
                ocms_flash_set('error', 'Risposta al captcha errata. Riprova.');
                $referer = $_SERVER['HTTP_REFERER'] ?? ocms_base_url() . '/';
                ocms_redirect($referer);
                return;
            }
            unset($_SESSION[$captchaKey]);

            // Collect submitted data
            $submission = ['id' => ocms_uuid(), 'submitted_at' => ocms_now(), 'data' => []];
            foreach ($form['fields'] as $field) {
                $key = $field['name'] ?? '';
                $submission['data'][$key] = trim($_POST[$key] ?? '');
            }

            $form['submissions'] = $form['submissions'] ?? [];
            $form['submissions'][] = $submission;
            $app->storage->save('forms', $params['slug'], $form);

            // Email notification via SMTP
            $email = $form['settings']['notify_email'] ?? '';
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $siteName = ocms_escape($app->config['site_name'] ?? 'O-CMS');
                $formName = ocms_escape($form['name'] ?? 'Form');
                $fieldsHtml = '';
                foreach ($submission['data'] as $k => $v) {
                    $fieldsHtml .= '<tr><td style="padding:8px 14px;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;width:140px;">' . htmlspecialchars($k) . '</td><td style="padding:8px 14px;border-bottom:1px solid #e5e7eb;">' . nl2br(htmlspecialchars($v)) . '</td></tr>';
                }
                $htmlBody = '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;"><div style="background:#4f46e5;padding:18px 24px;border-radius:10px 10px 0 0;"><h2 style="color:#fff;margin:0;font-size:1.1rem;">Nuova compilazione: ' . $formName . '</h2></div><div style="background:#fff;padding:20px 24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 10px 10px;"><table style="width:100%;border-collapse:collapse;font-size:0.9rem;">' . $fieldsHtml . '</table><p style="font-size:0.8rem;color:#888;margin-top:16px;">Inviato il ' . date('d/m/Y H:i') . ' — ' . $siteName . '</p></div></div>';
                @ocms_send_mail($email, "Nuova compilazione: {$form['name']}", $htmlBody);
            }

            $msg = $form['settings']['success_message'] ?? 'Grazie!';
            ocms_flash_set('success', $msg);
            $referer = $_SERVER['HTTP_REFERER'] ?? ocms_base_url() . '/';
            ocms_redirect($referer);
        });

        // ─── CONTACT FORM (AJAX with SMTP) ───
        $this->router->post('/contatto/invia', function () use ($app) {
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                echo json_encode(['success' => false, 'error' => 'Dati non validi']);
                return;
            }

            $name = trim($input['nome'] ?? '');
            $email = trim($input['email'] ?? '');
            $message = trim($input['messaggio'] ?? '');

            if (!$name || !$email || !$message) {
                echo json_encode(['success' => false, 'error' => 'Tutti i campi sono obbligatori.']);
                return;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Email non valida.']);
                return;
            }

            // Simple rate limiting: max 3 submissions per session
            $_SESSION['contact_count'] = ($_SESSION['contact_count'] ?? 0) + 1;
            if ($_SESSION['contact_count'] > 3) {
                echo json_encode(['success' => false, 'error' => 'Troppi messaggi inviati. Riprova più tardi.']);
                return;
            }

            $siteName = $app->config['site_name'] ?? 'O-CMS';
            $subject = "Nuovo messaggio da {$name} — {$siteName}";
            $htmlBody = '
            <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">
                <div style="background:#4f46e5;padding:20px 24px;border-radius:10px 10px 0 0;">
                    <h2 style="color:#fff;margin:0;font-size:1.2rem;">Nuovo messaggio dal sito</h2>
                </div>
                <div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 10px 10px;">
                    <p><strong>Nome:</strong> ' . htmlspecialchars($name) . '</p>
                    <p><strong>Email:</strong> <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></p>
                    <p><strong>Messaggio:</strong></p>
                    <div style="background:#f9fafb;padding:16px;border-radius:8px;border:1px solid #e5e7eb;">' . nl2br(htmlspecialchars($message)) . '</div>
                    <p style="font-size:0.8rem;color:#888;margin-top:16px;">Inviato il ' . date('d/m/Y H:i') . '</p>
                </div>
            </div>';

            $sent = ocms_send_mail($this->config['admin_email'] ?? '', $subject, $htmlBody);

            if ($sent) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Errore nell\'invio. Riprova più tardi.']);
            }
        });

        // ─── REGISTRATION ───

        $this->router->get('/register', function () use ($app) {
            $config = $app->storage->readFile('config.json') ?? [];
            if (empty($config['registration_enabled'])) {
                http_response_code(404);
                $app->render('404');
                return;
            }
            if ($app->auth->check()) {
                ocms_redirect(ocms_base_url() . '/');
                return;
            }
            Session::start();
            $captcha = ocms_captcha_generate();
            $app->render('register', ['captcha' => $captcha]);
        });

        $this->router->post('/register', function () use ($app) {
            $config = $app->storage->readFile('config.json') ?? [];
            if (empty($config['registration_enabled'])) {
                http_response_code(404);
                echo 'Registrazione disabilitata';
                return;
            }
            if (!ocms_rate_limit('register', 3, 600)) {
                ocms_flash_set('error', 'Troppi tentativi di registrazione. Riprova tra 10 minuti.');
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }

            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }

            // Verify captcha
            if (!ocms_captcha_verify($_POST['captcha'] ?? '')) {
                ocms_flash_set('error', 'Risposta captcha errata');
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }

            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $displayName = trim($_POST['display_name'] ?? $username);

            // Validation
            if (!$username || !$email || !$password) {
                ocms_flash_set('error', 'Tutti i campi sono obbligatori');
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }
            if (!preg_match('/^[a-z0-9_-]+$/', $username)) {
                ocms_flash_set('error', 'Username: solo lettere minuscole, numeri, - e _');
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ocms_flash_set('error', 'Email non valida');
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }
            if (strlen($password) < 6) {
                ocms_flash_set('error', 'La password deve avere almeno 6 caratteri');
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }

            $result = $app->auth->register([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'display_name' => $displayName,
            ]);

            if (!$result['success']) {
                ocms_flash_set('error', $result['error']);
                ocms_redirect(ocms_base_url() . '/register');
                return;
            }

            // Send activation email
            $siteUrl = $config['site_url'] ?? '';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $activateUrl = $protocol . '://' . $host . $siteUrl . '/activate/' . $result['token'];

            $siteName = $config['site_name'] ?? 'O-CMS';
            $htmlBody = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
    <h2 style="color:#6366f1;">Benvenuto su {$siteName}!</h2>
    <p>Ciao <strong>{$displayName}</strong>,</p>
    <p>Grazie per esserti registrato. Clicca il pulsante qui sotto per attivare il tuo account:</p>
    <p style="text-align:center;margin:30px 0;">
        <a href="{$activateUrl}" style="background:#6366f1;color:white;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block;">Attiva Account</a>
    </p>
    <p style="color:#666;font-size:0.9em;">Se non riesci a cliccare il pulsante, copia e incolla questo link nel browser:</p>
    <p style="color:#666;font-size:0.85em;word-break:break-all;">{$activateUrl}</p>
</div>
HTML;

            $mailSent = ocms_send_mail($email, "Attiva il tuo account su {$siteName}", $htmlBody);

            if ($mailSent) {
                ocms_flash_set('success', 'Registrazione completata! Controlla la tua email per attivare l\'account.');
            } else {
                ocms_flash_set('success', 'Registrazione completata! Contatta l\'amministratore per attivare il tuo account.');
            }
            ocms_redirect(ocms_base_url() . '/register');
        });

        // Account activation
        $this->router->get('/activate/{token}', function ($params) use ($app) {
            $token = $params['token'];
            if ($app->auth->activate($token)) {
                ocms_flash_set('success', 'Account attivato con successo! Ora puoi accedere.');
            } else {
                ocms_flash_set('error', 'Link di attivazione non valido o già utilizzato.');
            }
            ocms_redirect(ocms_base_url() . '/admin/login');
        });

        // Lessons listing
        $this->router->get('/lezioni', function () use ($app) {
            ocms_track_visit('/lezioni', 'page');
            $lessons = $app->storage->findAll('lessons', function ($l) {
                return ($l['status'] ?? '') === 'published';
            }, 'updated_at', 'desc');
            $app->render('lessons', ['lessons' => $lessons]);
        });

        // Single lesson
        $this->router->get('/lezione/{slug}', function ($params) use ($app) {
            $lesson = $app->storage->find('lessons', $params['slug']);
            if (!$lesson || $lesson['status'] !== 'published') {
                http_response_code(404);
                $app->render('404');
                return;
            }
            ocms_track_visit('/lezione/' . $params['slug'], 'lesson');

            $lesson['views'] = ($lesson['views'] ?? 0) + 1;
            $app->storage->save('lessons', $lesson['slug'], $lesson);

            // Scan lesson files
            $dir = ocms_base_path() . '/uploads/lezioni/' . $lesson['slug'];
            $files = [];
            if (is_dir($dir)) {
                $extMap = [
                    'html' => ['html', 'htm'],
                    'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                    'audio' => ['mp3', 'm4a', 'ogg', 'wav'],
                    'video' => ['mp4', 'webm', 'ogv'],
                    'pdf' => ['pdf'],
                    'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
                ];
                foreach (scandir($dir) as $item) {
                    if ($item[0] === '.' || is_dir($dir . '/' . $item)) continue;
                    if (in_array($item, ['info.txt', 'visite.txt']) || preg_match('/^index(_backup_\d+)?\.php$/', $item)) continue;
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    foreach ($extMap as $cat => $exts) {
                        if (in_array($ext, $exts)) {
                            $files[] = ['name' => $item, 'cat' => $cat, 'basename' => pathinfo($item, PATHINFO_FILENAME)];
                            break;
                        }
                    }
                }
            }

            // Group files by basename
            $groups = [];
            foreach ($files as $f) {
                $groups[$f['basename']][] = $f;
            }

            // Load descriptions from matching .txt files
            $groupDescs = [];
            foreach ($groups as $basename => $members) {
                $txtFile = $dir . '/' . $basename . '.txt';
                if (file_exists($txtFile)) {
                    $groupDescs[$basename] = trim(file_get_contents($txtFile));
                }
            }

            // Sort: main file first, then alphabetical
            $mainBasename = pathinfo($lesson['main_file'] ?? '', PATHINFO_FILENAME);
            uksort($groups, function ($a, $b) use ($mainBasename) {
                if ($a === $mainBasename) return -1;
                if ($b === $mainBasename) return 1;
                return strcasecmp($a, $b);
            });

            $app->render('lesson', [
                'lesson' => $lesson,
                'files' => $files,
                'groups' => $groups,
                'groupDescs' => $groupDescs,
                'mainBasename' => $mainBasename,
            ]);
        });

        // ─── GALLERIES (frontend) ───

        $this->router->get('/gallerie', function () use ($app) {
            ocms_track_visit('/gallerie', 'page');
            $galleries = $app->storage->findAll('galleries', function ($g) {
                return ($g['status'] ?? '') === 'published';
            }, 'updated_at', 'desc');

            $filterTag = $_GET['tag'] ?? '';
            $allTags = [];
            foreach ($galleries as $g) {
                foreach ($g['tags'] ?? [] as $tag) {
                    $tagLower = mb_strtolower(trim($tag));
                    if (!in_array($tagLower, array_map('mb_strtolower', $allTags))) $allTags[] = trim($tag);
                }
            }
            sort($allTags);

            if ($filterTag) {
                $galleries = array_filter($galleries, fn($g) => in_array($filterTag, array_map('mb_strtolower', $g['tags'] ?? [])));
                $galleries = array_values($galleries);
            }

            $app->render('galleries', ['galleries' => $galleries, 'allTags' => $allTags, 'filterTag' => $filterTag]);
        });

        $this->router->get('/galleria/{slug}', function ($params) use ($app) {
            $gallery = $app->storage->find('galleries', $params['slug']);
            if (!$gallery || $gallery['status'] !== 'published') {
                http_response_code(404);
                $app->render('404');
                return;
            }
            ocms_track_visit('/galleria/' . $params['slug'], 'gallery');
            $app->render('gallery', ['gallery' => $gallery]);
        });

        // ─── QUIZ (frontend) ───

        // List public quizzes
        $this->router->get('/quiz', function () use ($app) {
            ocms_track_visit('/quiz', 'page');
            $quizzes = $app->storage->findAll('quizzes', function ($q) {
                return ($q['status'] ?? '') === 'published';
            }, 'created_at', 'desc');
            $app->render('quizzes', ['quizzes' => $quizzes]);
        });

        // Single quiz
        $this->router->get('/quiz/{slug}', function ($params) use ($app) {
            $quiz = $app->storage->find('quizzes', $params['slug']);
            if (!$quiz || $quiz['status'] !== 'published') {
                http_response_code(404);
                $app->render('404');
                return;
            }

            // If authentication required and no access code, check login
            $hasAccessCode = !empty($quiz['settings']['access_code']);
            if (!empty($quiz['settings']['require_auth']) && !$hasAccessCode && !$app->auth->check()) {
                ocms_flash_set('error', 'Devi effettuare il login per accedere a questo quiz.');
                ocms_redirect(ocms_base_url() . '/admin/login');
                return;
            }

            // Is quiz active?
            $quizActive = $quiz['settings']['active'] ?? true;

            ocms_track_visit('/quiz/' . $params['slug'], 'quiz');
            $loggedUser = $app->auth->check() ? $app->auth->user() : null;
            $app->render('quiz', ['quiz' => $quiz, 'loggedUser' => $loggedUser, 'quizActive' => $quizActive]);
        });

        // Check quiz email (AJAX)
        $this->router->post('/quiz/{slug}/check-email', function ($params) use ($app) {
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            $email = strtolower(trim($input['email'] ?? ''));
            if (!$email) {
                echo json_encode(['taken' => false]);
                return;
            }
            $existing = $app->storage->findAll('quiz-results', function ($r) use ($params, $email) {
                return ($r['quiz_slug'] ?? '') === $params['slug']
                    && strtolower(trim($r['student']['email'] ?? '')) === $email;
            });
            echo json_encode(['taken' => !empty($existing)]);
        });

        // Submit quiz (AJAX)
        $this->router->post('/quiz/{slug}/submit', function ($params) use ($app) {
            header('Content-Type: application/json');
            $quiz = $app->storage->find('quizzes', $params['slug']);
            if (!$quiz || $quiz['status'] !== 'published') {
                echo json_encode(['success' => false, 'error' => 'Quiz non trovato']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                echo json_encode(['success' => false, 'error' => 'Dati non validi']);
                return;
            }

            // Verify CSRF token
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
                return;
            }

            // Is quiz active?
            if (isset($quiz['settings']['active']) && !$quiz['settings']['active']) {
                echo json_encode(['success' => false, 'error' => 'Quiz non attivo']);
                return;
            }

            $student = is_array($input['student'] ?? null) ? $input['student'] : [];

            // Check if email was already used for this quiz
            $studentEmail = strtolower(trim($student['email'] ?? ''));
            if ($studentEmail) {
                $existing = $app->storage->findAll('quiz-results', function ($r) use ($params, $studentEmail) {
                    return ($r['quiz_slug'] ?? '') === $params['slug']
                        && strtolower(trim($r['student']['email'] ?? '')) === $studentEmail;
                });
                if (!empty($existing)) {
                    echo json_encode(['success' => false, 'error' => 'Questa email ha già completato il quiz.']);
                    return;
                }
            }
            $resultId = ocms_uuid();
            $result = [
                'id' => $resultId,
                'quiz_slug' => $params['slug'],
                'student' => [
                    'name' => trim($student['name'] ?? ''),
                    'class' => trim($student['class'] ?? ''),
                    'email' => trim($student['email'] ?? ''),
                    'username' => $app->auth->check() ? $app->auth->user()['username'] : null,
                ],
                'answers' => is_array($input['answers'] ?? null) ? $input['answers'] : [],
                'score' => 0,
                'correct_count' => 0,
                'wrong_count' => 0,
                'skipped_count' => 0,
                'time_seconds' => max(0, intval($input['time_seconds'] ?? 0)),
                'submitted_at' => ocms_now(),
            ];

            // Calculate score server-side
            $questions = $quiz['questions'] ?? [];
            $correctCount = 0;
            $wrongCount = 0;
            $skippedCount = 0;
            foreach ($questions as $qi => $question) {
                $answer = $result['answers'][$qi] ?? null;
                if ($answer === null || $answer === '') {
                    $skippedCount++;
                } elseif ($answer === $question['correct']) {
                    $correctCount++;
                } else {
                    $wrongCount++;
                }
            }

            $penalty = !empty($quiz['settings']['penalty_mode']);
            if ($penalty) {
                $raw = $correctCount * 2 - $wrongCount;
                $max = count($questions) * 2;
                $score = $max > 0 ? max(0, ($raw / $max) * 100) : 0;
            } else {
                $score = count($questions) > 0 ? ($correctCount / count($questions)) * 100 : 0;
            }
            $score = round($score, 1);

            // Use server-side calculated values
            $result['score'] = $score;
            $result['correct_count'] = $correctCount;
            $result['wrong_count'] = $wrongCount;
            $result['skipped_count'] = $skippedCount;

            $app->storage->save('quiz-results', $resultId, $result);
            echo json_encode(['success' => true, 'id' => $resultId, 'score' => $score]);
        });

        // Single page (catch-all — MUST be the last frontend route)
        $this->router->get('/{slug}', function ($params) use ($app) {
            $page = $app->storage->find('pages', $params['slug']);
            if (!$page || $page['status'] !== 'published') {
                http_response_code(404);
                $app->render('404');
                return;
            }
            ocms_track_visit('/' . $params['slug'], 'page');

            // Increment page view counter
            $page['views'] = ($page['views'] ?? 0) + 1;
            $app->storage->save('pages', $page['slug'], $page);

            // If the page has an assigned layout, use the Layout Builder
            if (!empty($page['layout']) && $page['layout'] !== 'none') {
                $app->renderWithLayout($page);
            } else {
                $app->render('page', ['page' => $page]);
            }
        });
    }

    // ─── ADMIN ROUTES ───

    /**
     * Register all admin (back-office) routes.
     *
     * @return void
     */
    private function setupAdminRoutes(): void {
        $app = $this;

        // Login
        $this->router->get('/login', function () use ($app) {
            if ($app->auth->check()) {
                ocms_redirect(ocms_base_url() . '/admin');
            }
            $app->renderAdmin('login');
        });

        $this->router->post('/login', function () use ($app) {
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/login');
            }
            if (!ocms_rate_limit('login', 5, 300)) {
                ocms_flash_set('error', 'Troppi tentativi. Riprova tra qualche minuto.');
                ocms_redirect(ocms_base_url() . '/admin/login');
            }
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            $result = $app->auth->attempt($username, $password);
            if ($result['success']) {
                ocms_flash_set('success', 'Benvenuto!');
                ocms_redirect(ocms_base_url() . '/admin');
            } else {
                ocms_flash_set('error', $result['error']);
                ocms_redirect(ocms_base_url() . '/admin/login');
            }
        });

        // Logout (accepts both GET and POST for compatibility; the layout link uses POST)
        $this->router->any('/logout', function () use ($app) {
            $app->auth->logout();
            ocms_redirect(ocms_base_url() . '/admin/login');
        });

        // Dashboard
        $this->router->get('/', function () use ($app) {
            $app->auth->requireRole('administrator');
            $app->renderAdmin('dashboard', [
                'pages_count' => $app->storage->count('pages'),
                'articles_count' => $app->storage->count('articles'),
                'users_count' => $app->storage->count('users'),
            ]);
        });

        // ─── PAGES ───

        // List pages
        $this->router->get('/pages', function () use ($app) {
            $app->auth->requireRole('editor');
            $pages = $app->storage->findAll('pages', null, 'updated_at', 'desc');
            $app->renderAdmin('pages/index', ['pages' => $pages]);
        });

        // New page
        $this->router->get('/pages/new', function () use ($app) {
            $app->auth->requireRole('editor');
            $layouts = $app->storage->findAll('layouts', null, 'name', 'asc');
            $app->renderAdmin('pages/edit', ['page' => null, 'layouts' => $layouts]);
        });

        // Edit page
        $this->router->get('/pages/edit/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $page = $app->storage->find('pages', $params['slug']);
            if (!$page) {
                ocms_flash_set('error', 'Pagina non trovata');
                ocms_redirect(ocms_base_url() . '/admin/pages');
            }
            $layouts = $app->storage->findAll('layouts', null, 'name', 'asc');
            $app->renderAdmin('pages/edit', ['page' => $page, 'layouts' => $layouts]);
        });

        // Save page (create or update)
        $this->router->post('/pages/save', function () use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/pages');
            }

            $isNew = empty($_POST['original_slug']);
            $slug = ocms_slug($_POST['slug'] ?: $_POST['title']);
            $originalSlug = $_POST['original_slug'] ?? '';

            // If the slug changed, delete the old file
            if (!$isNew && $originalSlug && $originalSlug !== $slug) {
                $app->storage->delete('pages', $originalSlug);
            }

            // Load existing page to preserve extra fields (hero_image, sections, views)
            $existingPage = !$isNew ? $app->storage->find('pages', $originalSlug ?: $slug) : null;

            $page = [
                'id' => $_POST['id'] ?: ocms_uuid(),
                'title' => trim($_POST['title']),
                'slug' => $slug,
                'content' => ocms_sanitize_html($_POST['content'] ?? ''),
                'template' => $_POST['template'] ?? 'page',
                'layout' => $_POST['layout'] ?? 'none',
                'status' => $_POST['status'] ?? 'draft',
                'meta' => [
                    'title' => trim($_POST['meta_title'] ?? ''),
                    'description' => trim($_POST['meta_description'] ?? ''),
                    'og_image' => trim($_POST['og_image'] ?? ''),
                ],
                'order' => (int)($_POST['order'] ?? 0),
                'parent' => $_POST['parent'] ?: null,
                'author' => $app->auth->user()['username'],
                'created_at' => $_POST['created_at'] ?: ocms_now(),
                'updated_at' => ocms_now(),
            ];

            // Preserve extra fields not managed by the form
            if ($existingPage) {
                if (isset($existingPage['hero_image'])) $page['hero_image'] = $existingPage['hero_image'];
                if (isset($existingPage['sections'])) $page['sections'] = $existingPage['sections'];
                if (isset($existingPage['views'])) $page['views'] = $existingPage['views'];
            }

            $app->storage->save('pages', $slug, $page);
            ocms_flash_set('success', $isNew ? 'Pagina creata!' : 'Pagina aggiornata!');
            ocms_redirect(ocms_base_url() . '/admin/pages/edit/' . $slug);
        });

        // Delete page
        $this->router->post('/pages/delete/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/pages');
            }
            $app->storage->delete('pages', $params['slug']);
            ocms_flash_set('success', 'Pagina eliminata');
            ocms_redirect(ocms_base_url() . '/admin/pages');
        });

        // ─── ARTICLES ───

        $this->router->get('/articles', function () use ($app) {
            $app->auth->requireRole('editor');
            $articles = $app->storage->findAll('articles', null, 'updated_at', 'desc');
            $categories = $app->storage->findAll('categories', null, 'name', 'asc');
            $app->renderAdmin('articles/index', ['articles' => $articles, 'categories' => $categories]);
        });

        $this->router->get('/articles/new', function () use ($app) {
            $app->auth->requireRole('editor');
            $categories = $app->storage->findAll('categories', null, 'name', 'asc');
            $tags = $app->storage->findAll('tags', null, 'name', 'asc');
            $app->renderAdmin('articles/edit', ['article' => null, 'categories' => $categories, 'tags' => $tags]);
        });

        $this->router->get('/articles/edit/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $article = $app->storage->find('articles', $params['slug']);
            if (!$article) {
                ocms_flash_set('error', 'Articolo non trovato');
                ocms_redirect(ocms_base_url() . '/admin/articles');
            }
            $categories = $app->storage->findAll('categories', null, 'name', 'asc');
            $tags = $app->storage->findAll('tags', null, 'name', 'asc');
            $app->renderAdmin('articles/edit', ['article' => $article, 'categories' => $categories, 'tags' => $tags]);
        });

        $this->router->post('/articles/save', function () use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/articles');
            }

            $isNew = empty($_POST['original_slug']);
            $slug = ocms_slug($_POST['slug'] ?: $_POST['title']);
            $originalSlug = $_POST['original_slug'] ?? '';

            if (!$isNew && $originalSlug && $originalSlug !== $slug) {
                $app->storage->delete('articles', $originalSlug);
            }

            $tagsRaw = $_POST['tags'] ?? '';
            $tagsArray = array_filter(array_map('trim', explode(',', $tagsRaw)));

            // Auto-create tags if they don't exist
            foreach ($tagsArray as $tagName) {
                $tagSlug = ocms_slug($tagName);
                if ($tagSlug && !$app->storage->exists('tags', $tagSlug)) {
                    $app->storage->save('tags', $tagSlug, [
                        'name' => $tagName,
                        'slug' => $tagSlug,
                        'created_at' => ocms_now(),
                    ]);
                }
            }

            // Save a revision of the previous article version (if it exists)
            if (!$isNew) {
                $oldSlug = $originalSlug ?: $slug;
                $oldArticle = $app->storage->find('articles', $oldSlug);
                if ($oldArticle) {
                    $revDir = ocms_data_path('revisions/' . $slug);
                    if (!is_dir($revDir)) mkdir($revDir, 0755, true);
                    $revFile = $revDir . '/' . date('Y-m-d_H-i-s') . '.json';
                    file_put_contents($revFile, json_encode($oldArticle, JSON_PRETTY_PRINT), LOCK_EX);
                }
            }

            // Preserve view count from the existing article
            $existingViews = 0;
            if (!$isNew) {
                $existingArticle = $app->storage->find('articles', $originalSlug ?: $slug);
                if ($existingArticle) $existingViews = $existingArticle['views'] ?? 0;
            }

            $article = [
                'id' => $_POST['id'] ?: ocms_uuid(),
                'title' => trim($_POST['title']),
                'slug' => $slug,
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'content' => ocms_sanitize_html($_POST['content'] ?? ''),
                'cover_image' => trim($_POST['cover_image'] ?? ''),
                'gallery' => json_decode($_POST['gallery'] ?? '[]', true) ?: [],
                'category' => $_POST['category'] ?? '',
                'tags' => $tagsArray,
                'status' => $_POST['status'] ?? 'draft',
                'featured' => !empty($_POST['featured']),
                'publish_at' => !empty($_POST['publish_at']) ? (new DateTime($_POST['publish_at']))->format('c') : '',
                'meta' => [
                    'title' => trim($_POST['meta_title'] ?? ''),
                    'description' => trim($_POST['meta_description'] ?? ''),
                    'og_image' => trim($_POST['og_image'] ?? ''),
                ],
                'author' => $app->auth->user()['username'],
                'created_at' => $_POST['created_at'] ?: ocms_now(),
                'updated_at' => ocms_now(),
                'views' => $existingViews,
            ];

            $app->storage->save('articles', $slug, $article);
            ocms_flash_set('success', $isNew ? 'Articolo creato!' : 'Articolo aggiornato!');
            ocms_redirect(ocms_base_url() . '/admin/articles/edit/' . $slug);
        });

        $this->router->post('/articles/delete/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/articles');
            }
            $app->storage->delete('articles', $params['slug']);
            ocms_flash_set('success', 'Articolo eliminato');
            ocms_redirect(ocms_base_url() . '/admin/articles');
        });

        // ─── COVER IMAGE UPLOAD ───

        $this->router->post('/articles/upload-cover', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $token = $_POST['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            if (empty($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Nessun file o errore upload']);
                return;
            }

            $file = $_FILES['cover'];
            if ($file['size'] > 10 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => 'File troppo grande (max 10MB)']);
                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Solo immagini (JPEG, PNG, GIF, WebP)']);
                return;
            }

            $coverDir = ocms_base_path() . '/uploads/covers';
            if (!is_dir($coverDir)) mkdir($coverDir, 0755, true);

            $safeName = ocms_slug(pathinfo($file['name'], PATHINFO_FILENAME));
            $fileName = $safeName . '-' . time() . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.jpg';
            $destPath = $coverDir . '/' . $fileName;

            if (!ocms_resize_cover($file['tmp_name'], $destPath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Errore nel ridimensionamento dell\'immagine']);
                return;
            }

            $url = '/uploads/covers/' . $fileName;
            echo json_encode(['success' => true, 'url' => $url]);
        });

        // ─── AI CONTENT GENERATION ───

        $this->router->post('/articles/ai-generate', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            if (!ocms_rate_limit('ai_generate', 10, 600)) {
                echo json_encode(['error' => 'Troppi utilizzi. Riprova tra qualche minuto.']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            $prompt = trim($input['prompt'] ?? '');
            if (!$prompt) {
                http_response_code(400);
                echo json_encode(['error' => 'Inserisci un prompt']);
                return;
            }

            // Build the full prompt
            $lang = $app->config['language'] ?? 'it';
            $aiConfig = $app->config['ai'] ?? [];
            $aiInstructions = $aiConfig['instructions'] ?? $app->config['ai_instructions'] ?? '';
            $fullPrompt = "REGOLE DI FORMATO (obbligatorie): " .
                          "1) Rispondi SOLO con codice HTML puro, NESSUN testo prima o dopo. " .
                          "2) NON usare blocchi ``` markdown. " .
                          "3) NON scrivere introduzioni come 'Ecco l articolo'. " .
                          "4) Inizia DIRETTAMENTE con il primo tag HTML. " .
                          "5) Usa SOLO questi tag: h2, h3, p, strong, em, blockquote. " .
                          "6) NON usare h1, html, head, body, doctype. " .
                          "7) Aggiungi style='margin-bottom:16px;' ai tag h2 e h3. " .
                          "8) Scrivi in lingua {$lang}. ";
            if ($aiInstructions) {
                $fullPrompt .= "STILE DI SCRITTURA (obbligatorio): {$aiInstructions} ";
            }
            $fullPrompt .= "RICHIESTA: {$prompt}";

            $output = null;
            $provider = $aiConfig['provider'] ?? 'none';

            // Local Claude CLI
            if ($provider === 'cli') {
                $claudeScript = $app->config['ai_cli_script'] ?? '';
                if (!$claudeScript || !file_exists($claudeScript)) {
                    echo json_encode(['error' => 'Script CLI non configurato o non trovato. Controlla il path nelle Impostazioni.']);
                    return;
                }
                $tmpFile = tempnam(sys_get_temp_dir(), 'ocms_ai_');
                file_put_contents($tmpFile, $fullPrompt);
                chmod($tmpFile, 0644);
                $output = shell_exec("{$claudeScript} " . escapeshellarg($tmpFile) . " 2>&1");
                @unlink($tmpFile);
            } else {
                // Multi-provider API
                $apiKey = $aiConfig['api_key'] ?? '';
                $model = $aiConfig['model'] ?? '';

                if ($provider === 'none' || empty($apiKey)) {
                    echo json_encode(['error' => 'Nessun provider AI configurato. Vai nelle Impostazioni per attivarlo.']);
                    return;
                }

                // Default models per provider
                $defaultModels = [
                    'anthropic' => 'claude-sonnet-4-20250514',
                    'openai' => 'gpt-4o',
                    'google' => 'gemini-2.0-flash',
                    'mistral' => 'mistral-large-latest',
                    'groq' => 'llama-3.3-70b-versatile',
                ];
                if (empty($model)) $model = $defaultModels[$provider] ?? '';

                // Build the API request
                $headers = [];
                $url = '';
                $body = '';

                switch ($provider) {
                    case 'anthropic':
                        $url = 'https://api.anthropic.com/v1/messages';
                        $headers = [
                            'Content-Type: application/json',
                            'x-api-key: ' . $apiKey,
                            'anthropic-version: 2023-06-01',
                        ];
                        $body = json_encode([
                            'model' => $model,
                            'max_tokens' => 4096,
                            'messages' => [['role' => 'user', 'content' => $fullPrompt]],
                        ]);
                        break;

                    case 'google':
                        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;
                        $headers = ['Content-Type: application/json'];
                        $body = json_encode([
                            'contents' => [['parts' => [['text' => $fullPrompt]]]],
                        ]);
                        break;

                    case 'openai':
                    case 'mistral':
                    case 'groq':
                        $urls = [
                            'openai' => 'https://api.openai.com/v1/chat/completions',
                            'mistral' => 'https://api.mistral.ai/v1/chat/completions',
                            'groq' => 'https://api.groq.com/openai/v1/chat/completions',
                        ];
                        $url = $urls[$provider];
                        $headers = [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $apiKey,
                        ];
                        $body = json_encode([
                            'model' => $model,
                            'max_tokens' => 4096,
                            'messages' => [['role' => 'user', 'content' => $fullPrompt]],
                        ]);
                        break;

                    default:
                        echo json_encode(['error' => 'Provider AI non supportato: ' . $provider]);
                        return;
                }

                // Call the API
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", $headers),
                        'content' => $body,
                        'timeout' => 60,
                        'ignore_errors' => true,
                    ],
                ]);

                $response = @file_get_contents($url, false, $ctx);

                // Fallback to cURL if file_get_contents fails
                if ($response === false && function_exists('curl_init')) {
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => $headers,
                        CURLOPT_POSTFIELDS => $body,
                        CURLOPT_TIMEOUT => 60,
                        CURLOPT_SSL_VERIFYPEER => false,
                    ]);
                    $response = curl_exec($ch);
                    curl_close($ch);
                }

                if ($response === false) {
                    echo json_encode(['error' => 'Errore di connessione al provider AI']);
                    return;
                }

                $data = json_decode($response, true);

                // Extract error
                if (isset($data['error'])) {
                    $errMsg = is_array($data['error']) ? ($data['error']['message'] ?? 'Errore sconosciuto') : $data['error'];
                    echo json_encode(['error' => 'Errore API: ' . $errMsg]);
                    return;
                }

                // Extract content from the response
                $output = match ($provider) {
                    'anthropic' => $data['content'][0]['text'] ?? null,
                    'google' => $data['candidates'][0]['content']['parts'][0]['text'] ?? null,
                    default => $data['choices'][0]['message']['content'] ?? null,
                };
            }

            if ($output === null || trim($output) === '') {
                http_response_code(500);
                echo json_encode(['error' => 'Nessuna risposta dal provider AI. Riprova.']);
                return;
            }

            // Cleanup: remove markdown wrappers and extra text
            $content = trim($output);
            $content = preg_replace('/```(?:html)?\s*/i', '', $content);
            $content = preg_replace('/\s*```/', '', $content);
            $content = preg_replace('/^[^<]+/s', '', $content);
            $content = preg_replace('/>[^<>]*$/s', '>', $content);

            echo json_encode(['success' => true, 'content' => trim($content)]);
        });

        // ─── ANALYTICS ───

        $this->router->get('/analytics', function () use ($app) {
            $app->auth->requireRole('administrator');
            $days = (int)($_GET['days'] ?? 30);
            if ($days < 7) $days = 7;
            if ($days > 90) $days = 90;

            $analytics = ocms_analytics_range($days);
            $app->renderAdmin('analytics/index', [
                'analytics' => $analytics,
                'days' => $days,
            ]);
        });

        // ─── COMMENTS ───

        $this->router->get('/comments', function () use ($app) {
            $app->auth->requireRole('editor');
            $filter = $_GET['status'] ?? 'pending';
            $comments = $app->storage->findAll('comments', function ($c) use ($filter) {
                if ($filter === 'all') return true;
                return ($c['status'] ?? '') === $filter;
            }, 'created_at', 'desc');
            $app->renderAdmin('comments/index', ['comments' => $comments, 'filter' => $filter]);
        });

        $this->router->post('/comments/approve/{id}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF non valido']);
                return;
            }
            $comment = $app->storage->find('comments', $params['id']);
            if ($comment) {
                $comment['status'] = 'approved';
                $app->storage->save('comments', $params['id'], $comment);
            }
            echo json_encode(['success' => true]);
        });

        $this->router->post('/comments/reject/{id}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF non valido']);
                return;
            }
            $comment = $app->storage->find('comments', $params['id']);
            if ($comment) {
                $comment['status'] = 'rejected';
                $app->storage->save('comments', $params['id'], $comment);
            }
            echo json_encode(['success' => true]);
        });

        $this->router->post('/comments/delete/{id}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF non valido']);
                return;
            }
            $app->storage->delete('comments', $params['id']);
            echo json_encode(['success' => true]);
        });

        $this->router->post('/comments/reply/{id}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF non valido']);
                return;
            }
            $body = trim($input['body'] ?? '');
            if ($body === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Testo della risposta vuoto']);
                return;
            }
            $parent = $app->storage->find('comments', $params['id']);
            if (!$parent) {
                http_response_code(404);
                echo json_encode(['error' => 'Commento non trovato']);
                return;
            }
            $user = $app->auth->user();
            $replyId = 'c-' . time() . '-' . bin2hex(random_bytes(4));
            $reply = [
                'id' => $replyId,
                'article_slug' => $parent['article_slug'],
                'author_name' => $user['display_name'] ?? $user['username'] ?? 'Admin',
                'author_email' => $user['email'] ?? ($app->config['admin_email'] ?? ''),
                'body' => $body,
                'parent_id' => $parent['id'],
                'status' => 'approved',
                'created_at' => date('c'),
                'ip_hash' => '',
                'is_admin_reply' => true,
            ];
            $app->storage->save('comments', $replyId, $reply);
            echo json_encode(['success' => true, 'reply' => $reply]);
        });

        // ─── REVISIONS ───

        $this->router->get('/articles/revisions/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $article = $app->storage->find('articles', $params['slug']);
            if (!$article) {
                ocms_flash_set('error', 'Articolo non trovato');
                ocms_redirect(ocms_base_url() . '/admin/articles');
                return;
            }

            $revDir = ocms_data_path('revisions/' . $params['slug']);
            $revisions = [];
            if (is_dir($revDir)) {
                $files = glob($revDir . '/*.json');
                rsort($files);
                foreach ($files as $f) {
                    $rev = json_decode(file_get_contents($f), true);
                    if ($rev) {
                        $rev['_rev_file'] = basename($f, '.json');
                        $revisions[] = $rev;
                    }
                }
            }
            $app->renderAdmin('articles/revisions', ['article' => $article, 'revisions' => $revisions]);
        });

        $this->router->post('/articles/revisions/restore/{slug}/{rev}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token CSRF non valido');
                ocms_redirect(ocms_base_url() . '/admin/articles');
                return;
            }

            $revFile = ocms_data_path('revisions/' . $params['slug'] . '/' . $params['rev'] . '.json');
            if (!file_exists($revFile)) {
                ocms_flash_set('error', 'Revisione non trovata');
                ocms_redirect(ocms_base_url() . '/admin/articles/revisions/' . $params['slug']);
                return;
            }

            // Save current version as a new revision before restoring
            $current = $app->storage->find('articles', $params['slug']);
            if ($current) {
                $revDir = ocms_data_path('revisions/' . $params['slug']);
                $backupFile = $revDir . '/' . date('Y-m-d_H-i-s') . '.json';
                file_put_contents($backupFile, json_encode($current, JSON_PRETTY_PRINT), LOCK_EX);
            }

            $rev = json_decode(file_get_contents($revFile), true);
            $rev['updated_at'] = ocms_now();
            $app->storage->save('articles', $params['slug'], $rev);

            ocms_flash_set('success', 'Revisione ripristinata!');
            ocms_redirect(ocms_base_url() . '/admin/articles/edit/' . $params['slug']);
        });

        // ─── GALLERY UPLOAD ───

        $this->router->post('/articles/upload-gallery', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $token = $_POST['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            if (empty($_FILES['gallery_image']) || $_FILES['gallery_image']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Nessun file o errore upload']);
                return;
            }

            $file = $_FILES['gallery_image'];
            if ($file['size'] > 10 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => 'File troppo grande (max 10MB)']);
                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Solo immagini']);
                return;
            }

            $galleryDir = ocms_base_path() . '/uploads/gallery';
            if (!is_dir($galleryDir)) mkdir($galleryDir, 0755, true);

            $safeName = ocms_slug(pathinfo($file['name'], PATHINFO_FILENAME));
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'jpg';
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
            $fileName = $safeName . '-' . time() . '-' . $uniq . '.' . $ext;

            // Save full-size (resized to max 1600px)
            $fullPath = $galleryDir . '/' . $fileName;
            ocms_resize_image($file['tmp_name'], $fullPath, 1600, 1200);

            // Generate thumbnail (400x300)
            $thumbName = 'thumb-' . $fileName;
            $thumbExt = ($ext === 'png') ? $ext : 'jpg';
            $thumbName = pathinfo($thumbName, PATHINFO_FILENAME) . '.' . $thumbExt;
            $thumbPath = $galleryDir . '/' . $thumbName;
            ocms_resize_image($file['tmp_name'], $thumbPath, 400, 300);

            $url = '/uploads/gallery/' . $fileName;
            $thumbUrl = '/uploads/gallery/' . $thumbName;

            echo json_encode(['success' => true, 'url' => $url, 'thumb' => $thumbUrl, 'name' => $file['name']]);
        });

        // ─── CATEGORIES ───

        $this->router->get('/categories', function () use ($app) {
            $app->auth->requireRole('editor');
            $categories = $app->storage->findAll('categories', null, 'name', 'asc');
            // Count articles per category
            foreach ($categories as &$cat) {
                $catSlug = $cat['slug'];
                $cat['article_count'] = $app->storage->count('articles', fn($a) => ($a['category'] ?? '') === $catSlug);
            }
            $app->renderAdmin('categories/index', ['categories' => $categories]);
        });

        $this->router->post('/categories/save', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }
            $name = trim($input['name'] ?? '');
            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Nome obbligatorio']);
                return;
            }
            $slug = ocms_slug($name);
            $app->storage->save('categories', $slug, [
                'name' => $name,
                'slug' => $slug,
                'description' => trim($input['description'] ?? ''),
                'parent' => $input['parent'] ?? null,
                'created_at' => $input['created_at'] ?? ocms_now(),
            ]);
            echo json_encode(['success' => true, 'slug' => $slug, 'name' => $name]);
        });

        $this->router->post('/categories/delete/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/categories');
                return;
            }

            $catSlug = $params['slug'];
            $moveTo = $_POST['move_to'] ?? '';

            // Move or unset articles from this category
            $articles = $app->storage->findAll('articles', fn($a) => ($a['category'] ?? '') === $catSlug);
            foreach ($articles as $a) {
                $a['category'] = $moveTo ?: '';
                $a['updated_at'] = ocms_now();
                $app->storage->save('articles', $a['slug'], $a);
            }

            $app->storage->delete('categories', $catSlug);
            $msg = 'Categoria eliminata';
            if (count($articles) > 0) {
                $msg .= $moveTo
                    ? '. ' . count($articles) . ' articoli spostati'
                    : '. ' . count($articles) . ' articoli senza categoria';
            }
            ocms_flash_set('success', $msg);
            ocms_redirect(ocms_base_url() . '/admin/categories');
        });

        // Bulk-move articles to another category
        $this->router->post('/articles/bulk-move', function () use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/articles');
                return;
            }

            $slugs = $_POST['slugs'] ?? [];
            $newCat = $_POST['new_category'] ?? '';
            $returnCat = $_POST['return_cat'] ?? '';

            if (empty($slugs) || !is_array($slugs)) {
                ocms_flash_set('error', 'Nessun articolo selezionato');
                ocms_redirect(ocms_base_url() . '/admin/articles');
                return;
            }

            $category = $newCat === '__none__' ? '' : $newCat;
            $moved = 0;
            foreach ($slugs as $slug) {
                $article = $app->storage->find('articles', $slug);
                if ($article) {
                    $article['category'] = $category;
                    $article['updated_at'] = ocms_now();
                    $app->storage->save('articles', $slug, $article);
                    $moved++;
                }
            }

            ocms_flash_set('success', $moved . ' articoli spostati');
            $redirect = ocms_base_url() . '/admin/articles';
            if ($returnCat) $redirect .= '?cat=' . urlencode($returnCat);
            ocms_redirect($redirect);
        });

        // ─── MEDIA ───

        $this->router->get('/media', function () use ($app) {
            $app->auth->requireRole('editor');
            $media = $app->storage->findAll('media', null, 'uploaded_at', 'desc');
            $app->renderAdmin('media/index', ['media' => $media]);
        });

        $this->router->post('/media/upload', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $input = $_POST;
            $token = $input['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Nessun file o errore upload']);
                return;
            }

            $file = $_FILES['file'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'File troppo grande (max 10MB)']);
                return;
            }

            $allowedTypes = ['image/jpeg','image/png','image/gif','image/webp',
                             'application/pdf','text/plain','application/zip',
                             'video/mp4','audio/mpeg','audio/mp3'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tipo file non consentito: ' . $mimeType]);
                return;
            }

            // Determine upload sub-directory
            $subDir = str_starts_with($mimeType, 'image/') ? 'images' : (str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/') ? 'media' : 'documents');
            $uploadDir = ocms_base_path() . '/uploads/' . $subDir;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Safe filename with timestamp
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeName = ocms_slug(pathinfo($file['name'], PATHINFO_FILENAME));
            $uniq = substr(bin2hex(random_bytes(4)), 0, 8);
            $fileName = $safeName . '-' . time() . '-' . $uniq . '.' . $ext;
            $destPath = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Errore nel salvataggio del file']);
                return;
            }

            $url = '/uploads/' . $subDir . '/' . $fileName;
            $mediaId = ocms_slug($safeName) . '-' . time();

            // Save metadata
            $meta = [
                'id' => $mediaId,
                'filename' => $fileName,
                'original_name' => $file['name'],
                'url' => $url,
                'mime_type' => $mimeType,
                'size' => $file['size'],
                'alt' => '',
                'uploaded_by' => $app->auth->user()['username'],
                'uploaded_at' => ocms_now(),
            ];

            // Image dimensions
            if (str_starts_with($mimeType, 'image/')) {
                $dims = getimagesize($destPath);
                if ($dims) {
                    $meta['width'] = $dims[0];
                    $meta['height'] = $dims[1];
                }
            }

            $app->storage->save('media', $mediaId, $meta);
            echo json_encode(['success' => true, 'media' => $meta]);
        });

        $this->router->post('/media/delete/{id}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            $media = $app->storage->find('media', $params['id']);
            if ($media) {
                $filePath = ocms_base_path() . $media['url'];
                if (file_exists($filePath)) unlink($filePath);
                $app->storage->delete('media', $params['id']);
            }
            echo json_encode(['success' => true]);
        });

        // ─── MENUS ───

        // List menus
        $this->router->get('/menus', function () use ($app) {
            $app->auth->requireRole('editor');
            $menus = $app->storage->findAll('menus', null, 'name', 'asc');
            $app->renderAdmin('menus/index', ['menus' => $menus]);
        });

        // New menu
        $this->router->get('/menus/new', function () use ($app) {
            $app->auth->requireRole('editor');
            $pages = $app->storage->findAll('pages', fn($p) => $p['status'] === 'published', 'title', 'asc');
            $app->renderAdmin('menus/edit', ['menu' => null, 'pages' => $pages]);
        });

        // Edit menu
        $this->router->get('/menus/edit/{name}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $menu = $app->storage->find('menus', $params['name']);
            if (!$menu) {
                ocms_flash_set('error', 'Menu non trovato');
                ocms_redirect(ocms_base_url() . '/admin/menus');
            }
            $pages = $app->storage->findAll('pages', fn($p) => $p['status'] === 'published', 'title', 'asc');
            $app->renderAdmin('menus/edit', ['menu' => $menu, 'pages' => $pages]);
        });

        // Save menu (via AJAX — receives JSON)
        $this->router->post('/menus/save', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Dati non validi']);
                return;
            }

            $token = $input['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            $name = ocms_slug($input['name']);
            $originalName = $input['original_name'] ?? '';

            // If the name changed, delete the old one
            if ($originalName && $originalName !== $name) {
                $app->storage->delete('menus', $originalName);
            }

            $menu = [
                'name' => $name,
                'label' => trim($input['label'] ?? $input['name']),
                'items' => $input['items'] ?? [],
            ];

            $app->storage->save('menus', $name, $menu);
            echo json_encode(['success' => true, 'name' => $name]);
        });

        // Delete menu
        $this->router->post('/menus/delete/{name}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/menus');
            }
            $app->storage->delete('menus', $params['name']);
            ocms_flash_set('success', 'Menu eliminato');
            ocms_redirect(ocms_base_url() . '/admin/menus');
        });

        // ─── USERS ───

        $this->router->get('/users', function () use ($app) {
            $app->auth->requireRole('administrator');
            $users = $app->storage->findAll('users', null, 'created_at', 'desc');
            $app->renderAdmin('users/index', ['users' => $users]);
        });

        $this->router->get('/users/new', function () use ($app) {
            $app->auth->requireRole('administrator');
            $app->renderAdmin('users/edit', ['user' => null]);
        });

        $this->router->get('/users/edit/{username}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            $user = $app->storage->find('users', $params['username']);
            if (!$user) {
                ocms_flash_set('error', 'Utente non trovato');
                ocms_redirect(ocms_base_url() . '/admin/users');
            }
            $app->renderAdmin('users/edit', ['user' => $user]);
        });

        $this->router->post('/users/save', function () use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token CSRF non valido');
                ocms_redirect(ocms_base_url() . '/admin/users');
            }

            $username = trim($_POST['username'] ?? '');
            $isNew = !empty($_POST['is_new']);

            if ($isNew) {
                if ($app->storage->exists('users', $username)) {
                    ocms_flash_set('error', 'Username già in uso');
                    ocms_redirect(ocms_base_url() . '/admin/users/new');
                }
                $user = [
                    'id' => ocms_uuid(),
                    'username' => $username,
                    'email' => trim($_POST['email'] ?? ''),
                    'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    'display_name' => trim($_POST['display_name'] ?? $username),
                    'role' => $_POST['role'] ?? 'registered',
                    'avatar' => '',
                    'active' => true,
                    'created_at' => ocms_now(),
                    'last_login' => null,
                ];
            } else {
                $user = $app->storage->find('users', $username);
                if (!$user) {
                    ocms_flash_set('error', 'Utente non trovato');
                    ocms_redirect(ocms_base_url() . '/admin/users');
                }
                $user['email'] = trim($_POST['email'] ?? '');
                $user['display_name'] = trim($_POST['display_name'] ?? '');
                $user['role'] = $_POST['role'] ?? $user['role'];
                $user['active'] = isset($_POST['active']);
                if (!empty($_POST['password'])) {
                    $user['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
            }

            $app->storage->save('users', $username, $user);
            ocms_flash_set('success', $isNew ? 'Utente creato!' : 'Utente aggiornato!');
            ocms_redirect(ocms_base_url() . '/admin/users/edit/' . $username);
        });

        $this->router->post('/users/delete/{username}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token CSRF non valido');
                ocms_redirect(ocms_base_url() . '/admin/users');
            }
            if ($params['username'] === $app->auth->user()['username']) {
                ocms_flash_set('error', 'Non puoi eliminare te stesso');
                ocms_redirect(ocms_base_url() . '/admin/users');
            }
            $app->storage->delete('users', $params['username']);
            ocms_flash_set('success', 'Utente eliminato');
            ocms_redirect(ocms_base_url() . '/admin/users');
        });

        // ─── SETTINGS ───

        $this->router->get('/settings', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            $config = $app->storage->readFile('config.json') ?? [];
            $app->renderAdmin('settings/index', ['config' => $config]);
        });

        $this->router->post('/settings/save', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token CSRF non valido');
                ocms_redirect(ocms_base_url() . '/admin/settings');
            }

            $config = [
                'site_name' => trim($_POST['site_name'] ?? ''),
                'site_description' => trim($_POST['site_description'] ?? ''),
                'site_url' => rtrim(trim($_POST['site_url'] ?? ''), '/'),
                'theme' => $_POST['theme'] ?? 'flavor',
                'language' => $_POST['language'] ?? 'it',
                'timezone' => $_POST['timezone'] ?? 'Europe/Rome',
                'date_format' => $_POST['date_format'] ?? 'd/m/Y',
                'posts_per_page' => (int)($_POST['posts_per_page'] ?? 10),
                'admin_email' => trim($_POST['admin_email'] ?? ''),
                'maintenance_mode' => isset($_POST['maintenance_mode']),
                'registration_enabled' => isset($_POST['registration_enabled']),
                'site_width_value' => trim($_POST['site_width_value'] ?? '90'),
                'site_width_unit' => in_array($_POST['site_width_unit'] ?? '%', ['%', 'px']) ? $_POST['site_width_unit'] : '%',
                'smtp' => [
                    'method' => $_POST['smtp_method'] ?? 'smtp',
                    'host' => trim($_POST['smtp_host'] ?? ''),
                    'port' => (int)($_POST['smtp_port'] ?? 587),
                    'encryption' => $_POST['smtp_encryption'] ?? 'tls',
                    'username' => trim($_POST['smtp_username'] ?? ''),
                    'password' => trim($_POST['smtp_password'] ?? ''),
                    'from_email' => trim($_POST['smtp_from_email'] ?? ''),
                    'from_name' => trim($_POST['smtp_from_name'] ?? ''),
                ],
                'seo_global' => [
                    'robots' => $_POST['robots'] ?? "User-agent: *\nAllow: /",
                    'head_scripts' => $_POST['head_scripts'] ?? '',
                    'body_scripts' => $_POST['body_scripts'] ?? '',
                ],
                'ai_cli_script' => trim($_POST['ai_cli_script'] ?? ($app->config['ai_cli_script'] ?? '')),
                'ai' => [
                    'provider' => $_POST['ai_provider'] ?? ($app->config['ai']['provider'] ?? 'none'),
                    'api_key' => !empty(trim($_POST['ai_api_key'] ?? '')) ? trim($_POST['ai_api_key']) : ($app->config['ai']['api_key'] ?? ''),
                    'model' => trim($_POST['ai_model'] ?? ($app->config['ai']['model'] ?? '')),
                    'instructions' => trim($_POST['ai_instructions'] ?? ''),
                ],
            ];

            $app->storage->writeFile('config.json', $config);

            // Generate sitemap.xml
            $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            $baseUrl = $config['site_url'];
            $sitemap .= "  <url><loc>{$baseUrl}/</loc><priority>1.0</priority></url>\n";
            $pages = $app->storage->findAll('pages', fn($p) => $p['status'] === 'published');
            foreach ($pages as $p) {
                $sitemap .= "  <url><loc>{$baseUrl}/{$p['slug']}</loc><lastmod>{$p['updated_at']}</lastmod></url>\n";
            }
            $articles = $app->storage->findAll('articles', fn($a) => $a['status'] === 'published');
            foreach ($articles as $a) {
                $sitemap .= "  <url><loc>{$baseUrl}/blog/{$a['slug']}</loc><lastmod>{$a['updated_at']}</lastmod></url>\n";
            }
            $sitemap .= "</urlset>\n";
            file_put_contents(ocms_base_path() . '/sitemap.xml', $sitemap);

            // Generate robots.txt
            file_put_contents(ocms_base_path() . '/robots.txt', $config['seo_global']['robots'] ?? '');

            ocms_flash_set('success', 'Impostazioni salvate! Sitemap e robots.txt aggiornati.');
            ocms_redirect(ocms_base_url() . '/admin/settings');
        });

        // Test email delivery
        $this->router->post('/settings/test-email', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
            if (!$email) {
                echo json_encode(['success' => false, 'error' => 'Indirizzo email non valido']);
                return;
            }

            $config = $app->storage->readFile('config.json') ?? [];
            $smtp = $config['smtp'] ?? [];
            $method = $smtp['method'] ?? 'smtp';
            $fromEmail = $smtp['from_email'] ?? '';

            if (empty($fromEmail)) {
                echo json_encode(['success' => false, 'error' => 'Configura prima l\'email mittente e salva le impostazioni']);
                return;
            }

            if ($method === 'smtp' && empty($smtp['host'])) {
                echo json_encode(['success' => false, 'error' => 'Configura prima l\'host SMTP e salva le impostazioni']);
                return;
            }

            $siteName = $config['site_name'] ?? 'O-CMS';
            $htmlBody = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
                . '<h2 style="color:#6366f1;">Test Email - ' . htmlspecialchars($siteName) . '</h2>'
                . '<p>Se stai leggendo questa email, la configurazione (' . htmlspecialchars($method === 'smtp' ? 'SMTP' : 'PHP mail()') . ') funziona correttamente.</p>'
                . '<p style="color:#666;font-size:0.85em;">Inviata il ' . date('d/m/Y H:i:s') . '</p>'
                . '</div>';

            $sent = ocms_send_mail($email, "Test Email - {$siteName}", $htmlBody);

            if ($sent) {
                echo json_encode(['success' => true]);
            } else {
                $hint = $method === 'smtp'
                    ? 'Verifica host, porta, credenziali e crittografia SMTP.'
                    : 'La funzione PHP mail() ha restituito errore. Verifica che il server abbia sendmail/postfix configurato.';
                echo json_encode(['success' => false, 'error' => 'Invio fallito. ' . $hint]);
            }
        });

        // Test AI connection
        $this->router->post('/settings/test-ai', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            header('Content-Type: application/json');
            set_time_limit(60);

            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
                return;
            }

            $config = $app->storage->readFile('config.json') ?? [];
            $aiConfig = $config['ai'] ?? [];
            $provider = $aiConfig['provider'] ?? 'none';

            if ($provider === 'none') {
                echo json_encode(['success' => false, 'error' => 'Nessun provider AI configurato']);
                return;
            }

            $testPrompt = 'Rispondi solo con la frase: "O-CMS AI funziona correttamente." Nessun altro testo.';

            if ($provider === 'cli') {
                $script = $config['ai_cli_script'] ?? '';
                if (!$script) {
                    echo json_encode(['success' => false, 'error' => 'Path script CLI non configurato']);
                    return;
                }
                if (!file_exists($script)) {
                    echo json_encode(['success' => false, 'error' => 'Script non trovato: ' . $script]);
                    return;
                }
                if (!is_executable($script)) {
                    echo json_encode(['success' => false, 'error' => 'Script non eseguibile: ' . $script . '. Esegui chmod +x']);
                    return;
                }
                $tmpFile = tempnam(sys_get_temp_dir(), 'ocms_ai_test_');
                file_put_contents($tmpFile, $testPrompt);
                chmod($tmpFile, 0644);
                $output = shell_exec("{$script} " . escapeshellarg($tmpFile) . " 2>&1");
                @unlink($tmpFile);
                if ($output && strlen(trim($output)) > 0) {
                    echo json_encode(['success' => true, 'message' => 'Claude CLI funziona. Risposta: ' . ocms_truncate(trim($output), 100)]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Nessuna risposta dallo script CLI. Verifica che claude sia installato e autenticato.']);
                }
                return;
            }

            // Test API provider
            $apiKey = $aiConfig['api_key'] ?? '';
            if (empty($apiKey)) {
                echo json_encode(['success' => false, 'error' => 'API key non configurata']);
                return;
            }

            $model = $aiConfig['model'] ?? '';
            $defaultModels = [
                'anthropic' => 'claude-sonnet-4-20250514',
                'openai' => 'gpt-4o',
                'google' => 'gemini-2.0-flash',
                'mistral' => 'mistral-large-latest',
                'groq' => 'llama-3.3-70b-versatile',
            ];
            if (empty($model)) $model = $defaultModels[$provider] ?? '';

            $url = '';
            $headers = [];
            $body = '';
            $providerName = ucfirst($provider);

            switch ($provider) {
                case 'anthropic':
                    $url = 'https://api.anthropic.com/v1/messages';
                    $headers = ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'];
                    $body = json_encode(['model' => $model, 'max_tokens' => 100, 'messages' => [['role' => 'user', 'content' => $testPrompt]]]);
                    break;
                case 'openai':
                case 'groq':
                    $url = $provider === 'groq' ? 'https://api.groq.com/openai/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
                    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
                    $body = json_encode(['model' => $model, 'max_tokens' => 100, 'messages' => [['role' => 'user', 'content' => $testPrompt]]]);
                    break;
                case 'google':
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                    $headers = ['Content-Type: application/json'];
                    $body = json_encode(['contents' => [['parts' => [['text' => $testPrompt]]]]]);
                    break;
                case 'mistral':
                    $url = 'https://api.mistral.ai/v1/chat/completions';
                    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
                    $body = json_encode(['model' => $model, 'max_tokens' => 100, 'messages' => [['role' => 'user', 'content' => $testPrompt]]]);
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Provider sconosciuto: ' . $provider]);
                    return;
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                echo json_encode(['success' => false, 'error' => "Errore di rete: {$curlError}"]);
                return;
            }

            if ($httpCode >= 400) {
                $errData = json_decode($response, true);
                $errMsg = $errData['error']['message'] ?? $errData['error'] ?? "HTTP {$httpCode}";
                if (is_array($errMsg)) $errMsg = json_encode($errMsg);
                echo json_encode(['success' => false, 'error' => "{$providerName}: {$errMsg}"]);
                return;
            }

            $data = json_decode($response, true);
            $text = '';
            switch ($provider) {
                case 'anthropic':
                    $text = $data['content'][0]['text'] ?? '';
                    break;
                case 'openai': case 'groq': case 'mistral':
                    $text = $data['choices'][0]['message']['content'] ?? '';
                    break;
                case 'google':
                    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    break;
            }

            if ($text) {
                echo json_encode(['success' => true, 'message' => "{$providerName} ({$model}) OK. Risposta: " . ocms_truncate(trim($text), 100)]);
            } else {
                echo json_encode(['success' => false, 'error' => "{$providerName} ha risposto ma senza contenuto. Risposta raw: " . ocms_truncate($response, 200)]);
            }
        });

        // ─── FORM BUILDER ───

        $this->router->get('/forms', function () use ($app) {
            $app->auth->requireRole('editor');
            $forms = $app->storage->findAll('forms', null, 'created_at', 'desc');
            $app->renderAdmin('forms/index', ['forms' => $forms]);
        });

        $this->router->get('/forms/new', function () use ($app) {
            $app->auth->requireRole('editor');
            $app->renderAdmin('forms/edit', ['form' => null]);
        });

        $this->router->get('/forms/edit/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $form = $app->storage->find('forms', $params['slug']);
            if (!$form) { ocms_flash_set('error', 'Form non trovato'); ocms_redirect(ocms_base_url() . '/admin/forms'); }
            $app->renderAdmin('forms/edit', ['form' => $form]);
        });

        $this->router->post('/forms/save', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403); echo json_encode(['error' => 'CSRF']); return;
            }
            $slug = ocms_slug($input['name'] ?? 'form');
            $form = [
                'id' => $input['id'] ?? ocms_uuid(),
                'name' => trim($input['name'] ?? ''),
                'slug' => $slug,
                'fields' => $input['fields'] ?? [],
                'settings' => $input['settings'] ?? ['notify_email' => '', 'success_message' => 'Grazie! Il modulo è stato inviato.', 'submit_label' => 'Invia'],
                'submissions' => $input['submissions'] ?? [],
                'created_at' => $input['created_at'] ?? ocms_now(),
                'updated_at' => ocms_now(),
            ];
            $app->storage->save('forms', $slug, $form);
            echo json_encode(['success' => true, 'slug' => $slug]);
        });

        $this->router->get('/forms/submissions/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $form = $app->storage->find('forms', $params['slug']);
            if (!$form) { ocms_flash_set('error', 'Form non trovato'); ocms_redirect(ocms_base_url() . '/admin/forms'); }
            $app->renderAdmin('forms/submissions', ['form' => $form]);
        });

        $this->router->post('/forms/delete/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/forms'); }
            $app->storage->delete('forms', $params['slug']);
            ocms_flash_set('success', 'Form eliminato');
            ocms_redirect(ocms_base_url() . '/admin/forms');
        });

        // ─── LESSONS ───

        $this->router->get('/lessons', function () use ($app) {
            $app->auth->requireRole('editor');
            $lessons = $app->storage->findAll('lessons', null, 'updated_at', 'desc');
            $app->renderAdmin('lessons/index', ['lessons' => $lessons]);
        });

        $this->router->get('/lessons/new', function () use ($app) {
            $app->auth->requireRole('editor');
            $app->renderAdmin('lessons/edit', ['lesson' => null]);
        });

        $this->router->get('/lessons/edit/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $lesson = $app->storage->find('lessons', $params['slug']);
            if (!$lesson) {
                ocms_flash_set('error', 'Lezione non trovata');
                ocms_redirect(ocms_base_url() . '/admin/lessons');
            }
            $app->renderAdmin('lessons/edit', ['lesson' => $lesson]);
        });

        $this->router->post('/lessons/save', function () use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/lessons');
            }

            $isNew = empty($_POST['original_slug']);
            $slug = ocms_slug($_POST['slug'] ?: $_POST['title']);
            $originalSlug = $_POST['original_slug'] ?? '';

            $uploadDir = ocms_base_path() . '/uploads/lezioni/' . $slug;

            // If the slug changed, rename the folder and delete the old JSON
            if (!$isNew && $originalSlug && $originalSlug !== $slug) {
                $oldDir = ocms_base_path() . '/uploads/lezioni/' . $originalSlug;
                if (is_dir($oldDir)) {
                    rename($oldDir, $uploadDir);
                }
                $app->storage->delete('lessons', $originalSlug);
            }

            // Create the folder if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
                chown($uploadDir, 'www-data');
            }

            // Handle multiple file uploads
            if (!empty($_FILES['files']['name'][0])) {
                foreach ($_FILES['files']['name'] as $i => $name) {
                    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                    move_uploaded_file($_FILES['files']['tmp_name'][$i], $uploadDir . '/' . $safeName);
                }
            }

            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

            // Preserve view count from the existing lesson
            $existingViews = 0;
            if (!$isNew) {
                $existingLesson = $app->storage->find('lessons', $originalSlug ?: $slug);
                if ($existingLesson) $existingViews = $existingLesson['views'] ?? 0;
            }

            $lesson = [
                'id' => $_POST['id'] ?: ocms_uuid(),
                'title' => trim($_POST['title']),
                'slug' => $slug,
                'description' => trim($_POST['description'] ?? ''),
                'tags' => $tags,
                'main_file' => trim($_POST['main_file'] ?? ''),
                'status' => $_POST['status'] ?? 'draft',
                'author' => $app->auth->user()['username'],
                'created_at' => $_POST['created_at'] ?: ocms_now(),
                'updated_at' => ocms_now(),
                'views' => $existingViews,
            ];

            $app->storage->save('lessons', $slug, $lesson);
            ocms_flash_set('success', $isNew ? 'Lezione creata!' : 'Lezione aggiornata!');
            ocms_redirect(ocms_base_url() . '/admin/lessons/edit/' . $slug);
        });

        $this->router->post('/lessons/delete/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'CSRF');
                ocms_redirect(ocms_base_url() . '/admin/lessons');
            }
            // Delete lesson files directory
            $dir = ocms_base_path() . '/uploads/lezioni/' . $params['slug'];
            if (is_dir($dir)) {
                $files = glob($dir . '/*');
                foreach ($files as $f) { if (is_file($f)) unlink($f); }
                rmdir($dir);
            }
            $app->storage->delete('lessons', $params['slug']);
            ocms_flash_set('success', 'Lezione eliminata');
            ocms_redirect(ocms_base_url() . '/admin/lessons');
        });

        // Upload lesson files (AJAX)
        $this->router->post('/lessons/upload-files/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $lesson = $app->storage->find('lessons', $params['slug']);
            if (!$lesson) {
                echo json_encode(['error' => 'Lezione non trovata']);
                return;
            }

            $slug = basename($params['slug']);
            $uploadDir = ocms_base_path() . '/uploads/lezioni/' . $slug;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

            $uploaded = [];
            if (!empty($_FILES['files']['name'][0])) {
                foreach ($_FILES['files']['name'] as $i => $name) {
                    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                    $destPath = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $destPath)) {
                        @chown($destPath, 'www-data');
                        $uploaded[] = $safeName;
                    }
                }
            }

            echo json_encode(['success' => true, 'files' => $uploaded]);
        });

        // Delete lesson file (AJAX)
        $this->router->post('/lessons/delete-file/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);
            $fileName = basename($input['file'] ?? '');
            if (!$fileName) {
                echo json_encode(['error' => 'Nome file mancante']);
                return;
            }

            $slug = basename($params['slug']);
            $filePath = ocms_base_path() . '/uploads/lezioni/' . $slug . '/' . $fileName;
            if (file_exists($filePath)) {
                unlink($filePath);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'File non trovato']);
            }
        });

        // ─── QUIZZES (admin) ───

        // List quizzes
        $this->router->get('/quizzes', function () use ($app) {
            $app->auth->requireRole('editor');
            $quizzes = $app->storage->findAll('quizzes', null, 'created_at', 'desc');
            $app->renderAdmin('quizzes/index', ['quizzes' => $quizzes]);
        });

        // New quiz
        $this->router->get('/quizzes/new', function () use ($app) {
            $app->auth->requireRole('editor');
            $app->renderAdmin('quizzes/edit', ['quiz' => null]);
        });

        // Edit quiz
        $this->router->get('/quizzes/edit/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $quiz = $app->storage->find('quizzes', $params['slug']);
            if (!$quiz) {
                ocms_flash_set('error', 'Quiz non trovato');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
            }
            $app->renderAdmin('quizzes/edit', ['quiz' => $quiz]);
        });

        // Save quiz
        $this->router->post('/quizzes/save', function () use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
            }

            $isNew = empty($_POST['original_slug']);
            $slug = ocms_slug($_POST['slug'] ?: $_POST['title']);
            $originalSlug = $_POST['original_slug'] ?? '';

            if (!$isNew && $originalSlug && $originalSlug !== $slug) {
                $app->storage->delete('quizzes', $originalSlug);
            }

            $existingQuiz = !$isNew ? $app->storage->find('quizzes', $originalSlug ?: $slug) : null;

            // Process questions
            $questions = [];
            if (!empty($_POST['questions']) && is_array($_POST['questions'])) {
                foreach ($_POST['questions'] as $q) {
                    if (empty(trim($q['text'] ?? ''))) continue;
                    $questions[] = [
                        'text' => trim($q['text']),
                        'correct' => trim($q['correct'] ?? ''),
                        'wrong' => array_map('trim', $q['wrong'] ?? ['', '', '']),
                    ];
                }
            }

            $quiz = [
                'id' => $_POST['id'] ?: ocms_uuid(),
                'title' => trim($_POST['title']),
                'slug' => $slug,
                'description' => trim($_POST['description'] ?? ''),
                'questions' => $questions,
                'settings' => [
                    'penalty_mode' => !empty($_POST['settings']['penalty_mode']),
                    'randomize' => !empty($_POST['settings']['randomize']),
                    'time_limit' => max(0, intval($_POST['settings']['time_limit'] ?? 0)),
                    'require_auth' => !empty($_POST['settings']['require_auth']),
                    'active' => !empty($_POST['settings']['active']),
                    'access_code' => trim($_POST['settings']['access_code'] ?? ''),
                ],
                'status' => $_POST['status'] ?? 'draft',
                'author' => $app->auth->user()['username'],
                'created_at' => $existingQuiz['created_at'] ?? ocms_now(),
                'updated_at' => ocms_now(),
            ];

            // Auto-generate access code if empty
            if (empty($quiz['settings']['access_code'])) {
                $quiz['settings']['access_code'] = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }

            $app->storage->save('quizzes', $slug, $quiz);
            ocms_flash_set('success', $isNew ? 'Quiz creato!' : 'Quiz aggiornato!');
            ocms_redirect(ocms_base_url() . '/admin/quizzes/edit/' . $slug);
        });

        // Delete quiz
        $this->router->post('/quizzes/delete/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
                return;
            }
            $quiz = $app->storage->find('quizzes', $params['slug']);
            if (!$quiz) {
                ocms_flash_set('error', 'Quiz non trovato');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
                return;
            }
            // Also delete associated quiz results
            $results = $app->storage->findAll('quiz-results', fn($r) => ($r['quiz_slug'] ?? '') === $params['slug']);
            foreach ($results as $r) {
                $app->storage->delete('quiz-results', $r['id']);
            }
            $app->storage->delete('quizzes', $params['slug']);
            ocms_flash_set('success', 'Quiz eliminato');
            ocms_redirect(ocms_base_url() . '/admin/quizzes');
        });

        // Quiz results listing
        $this->router->get('/quizzes/results/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $quiz = $app->storage->find('quizzes', $params['slug']);
            if (!$quiz) {
                ocms_flash_set('error', 'Quiz non trovato');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
            }
            $results = $app->storage->findAll('quiz-results', fn($r) => ($r['quiz_slug'] ?? '') === $params['slug']);
            // Sort by submission date descending
            usort($results, fn($a, $b) => strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? ''));
            $app->renderAdmin('quizzes/results', ['quiz' => $quiz, 'results' => $results]);
        });

        // Delete a single quiz result
        $this->router->post('/quizzes/delete-result/{id}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
                return;
            }
            $result = $app->storage->find('quiz-results', $params['id']);
            if ($result) {
                $quizSlug = $result['quiz_slug'] ?? '';
                $app->storage->delete('quiz-results', $params['id']);
                ocms_flash_set('success', 'Risultato eliminato');
                ocms_redirect(ocms_base_url() . '/admin/quizzes/results/' . $quizSlug);
            } else {
                ocms_flash_set('error', 'Risultato non trovato');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
            }
        });

        // Send quiz result report via email
        $this->router->post('/quizzes/send-result/{id}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
                return;
            }

            $result = $app->storage->find('quiz-results', $params['id']);
            if (!$result) {
                ocms_flash_set('error', 'Risultato non trovato');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
                return;
            }

            $quizSlug = $_POST['quiz_slug'] ?? ($result['quiz_slug'] ?? '');
            $quiz = $app->storage->find('quizzes', $quizSlug);
            if (!$quiz) {
                ocms_flash_set('error', 'Quiz non trovato');
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
                return;
            }

            $email = $result['student']['email'] ?? '';
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ocms_flash_set('error', 'Email studente non valida o mancante');
                ocms_redirect(ocms_base_url() . '/admin/quizzes/results/' . $quizSlug);
                return;
            }

            // Build the HTML email report
            $studentName = ocms_escape($result['student']['name'] ?? 'Studente');
            $studentClass = ocms_escape($result['student']['class'] ?? '');
            $score = round($result['score'] ?? 0, 1);
            $correct = $result['correct_count'] ?? 0;
            $wrong = $result['wrong_count'] ?? 0;
            $skipped = $result['skipped_count'] ?? 0;
            $total = count($quiz['questions'] ?? []);
            $mins = intdiv($result['time_seconds'] ?? 0, 60);
            $secs = ($result['time_seconds'] ?? 0) % 60;
            $submittedAt = ocms_format_date($result['submitted_at'] ?? '', 'd/m/Y H:i');
            $siteName = ocms_escape($app->config['site_name'] ?? 'O-CMS');
            $quizTitle = ocms_escape($quiz['title']);
            $scoreColor = $score >= 60 ? '#22c55e' : '#ef4444';

            $questionsHtml = '';
            $questions = $quiz['questions'] ?? [];
            $answers = $result['answers'] ?? [];
            foreach ($questions as $qi => $question) {
                $studentAnswer = $answers[$qi] ?? null;
                $isCorrect = $studentAnswer === $question['correct'];
                $isSkipped = $studentAnswer === null || $studentAnswer === '';
                $statusColor = $isSkipped ? '#888' : ($isCorrect ? '#22c55e' : '#ef4444');
                $statusIcon = $isSkipped ? '&#9898;' : ($isCorrect ? '&#9989;' : '&#10060;');
                $answerText = $isSkipped ? '<em>Non risposta</em>' : ocms_escape($studentAnswer);
                $correctText = ocms_escape($question['correct']);
                $questionText = ocms_escape($question['text']);

                $questionsHtml .= '<tr>';
                $questionsHtml .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;font-weight:700;color:#888;">' . ($qi + 1) . '</td>';
                $questionsHtml .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;">' . $questionText . '</td>';
                $questionsHtml .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;color:' . $statusColor . ';">' . $answerText . '</td>';
                $questionsHtml .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;color:#22c55e;">' . $correctText . '</td>';
                $questionsHtml .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;">' . $statusIcon . '</td>';
                $questionsHtml .= '</tr>';
            }

            $htmlBody = '
            <div style="max-width:700px;margin:0 auto;font-family:Arial,sans-serif;color:#333;">
                <div style="background:linear-gradient(135deg,#4f46e5,#06b6d4);padding:24px 32px;border-radius:12px 12px 0 0;">
                    <h1 style="color:#fff;margin:0;font-size:1.4rem;">Risultato Quiz</h1>
                    <p style="color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:0.9rem;">' . $quizTitle . '</p>
                </div>
                <div style="background:#fff;padding:24px 32px;border:1px solid #e5e7eb;border-top:none;">
                    <p>Ciao <strong>' . $studentName . '</strong>,</p>
                    <p>ecco il report dettagliato del quiz che hai completato il ' . $submittedAt . '.</p>

                    <div style="display:flex;gap:16px;margin:20px 0;">
                        <div style="text-align:center;padding:16px 24px;background:#f9fafb;border-radius:10px;flex:1;">
                            <div style="font-size:2rem;font-weight:800;color:' . $scoreColor . ';">' . $score . '</div>
                            <div style="font-size:0.75rem;color:#888;text-transform:uppercase;">Punteggio</div>
                        </div>
                        <div style="text-align:center;padding:16px 24px;background:#f9fafb;border-radius:10px;flex:1;">
                            <div style="font-size:1.4rem;font-weight:700;color:#22c55e;">' . $correct . '/' . $total . '</div>
                            <div style="font-size:0.75rem;color:#888;text-transform:uppercase;">Corrette</div>
                        </div>
                        <div style="text-align:center;padding:16px 24px;background:#f9fafb;border-radius:10px;flex:1;">
                            <div style="font-size:1.4rem;font-weight:700;">' . sprintf('%d:%02d', $mins, $secs) . '</div>
                            <div style="font-size:0.75rem;color:#888;text-transform:uppercase;">Tempo</div>
                        </div>
                    </div>

                    <h3 style="margin:24px 0 12px;font-size:1rem;">Dettaglio risposte</h3>
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                        <thead>
                            <tr style="background:#f3f4f6;">
                                <th style="padding:8px 12px;text-align:center;width:30px;">#</th>
                                <th style="padding:8px 12px;text-align:left;">Domanda</th>
                                <th style="padding:8px 12px;text-align:left;">Tua risposta</th>
                                <th style="padding:8px 12px;text-align:left;">Risposta corretta</th>
                                <th style="padding:8px 12px;text-align:center;width:30px;"></th>
                            </tr>
                        </thead>
                        <tbody>' . $questionsHtml . '</tbody>
                    </table>
                </div>
                <div style="background:#f9fafb;padding:16px 32px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;text-align:center;">
                    <p style="font-size:0.8rem;color:#888;margin:0;">' . $siteName . '</p>
                </div>
            </div>';

            $subject = 'Risultato Quiz: ' . $quiz['title'] . ' — ' . $score . '/100';
            $sent = ocms_send_mail($email, $subject, $htmlBody);

            if ($sent) {
                // Save the email sent timestamp in the result
                $result['email_sent_at'] = date('c');
                $app->storage->save('quiz-results', $result['id'], $result);
                ocms_flash_set('success', 'Report inviato a ' . $email);
            } else {
                ocms_flash_set('error', 'Errore nell\'invio dell\'email a ' . $email);
            }
            ocms_redirect(ocms_base_url() . '/admin/quizzes/results/' . $quizSlug);
        });

        // Export quiz results as CSV
        $this->router->get('/quizzes/export-csv/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $quiz = $app->storage->find('quizzes', $params['slug']);
            if (!$quiz) {
                ocms_redirect(ocms_base_url() . '/admin/quizzes');
                return;
            }
            $results = $app->storage->findAll('quiz-results', fn($r) => ($r['quiz_slug'] ?? '') === $params['slug']);
            usort($results, fn($a, $b) => strcmp($a['student']['name'] ?? '', $b['student']['name'] ?? ''));

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="Risultati_' . $params['slug'] . '_' . date('Y-m-d') . '.csv"');
            // UTF-8 BOM for Excel compatibility
            echo "\xEF\xBB\xBF";
            echo "Nome,Classe,Voto,Corrette,Errate,Saltate,Tempo (sec),Tempo (mm:ss),Data\n";
            foreach ($results as $r) {
                $mins = intdiv($r['time_seconds'] ?? 0, 60);
                $secs = ($r['time_seconds'] ?? 0) % 60;
                $student = is_array($r['student'] ?? null) ? $r['student'] : [];
                $fields = [
                    (string)($student['name'] ?? ''),
                    (string)($student['class'] ?? ''),
                    (string)round($r['score'] ?? 0, 1),
                    (string)($r['correct_count'] ?? 0),
                    (string)($r['wrong_count'] ?? 0),
                    (string)($r['skipped_count'] ?? 0),
                    (string)($r['time_seconds'] ?? 0),
                    sprintf('%d:%02d', $mins, $secs),
                    (string)($r['submitted_at'] ?? ''),
                ];
                echo implode(',', array_map(function(string $f) {
                    $f = str_replace('"', '""', $f);
                    return '"' . $f . '"';
                }, $fields)) . "\n";
            }
        });

        // ─── GALLERIES ───

        // List all galleries
        $this->router->get('/galleries', function () use ($app) {
            $app->auth->requireRole('editor');
            $galleries = $app->storage->findAll('galleries', null, 'updated_at', 'desc');
            $app->renderAdmin('galleries/index', ['galleries' => $galleries]);
        });

        $this->router->get('/galleries/new', function () use ($app) {
            $app->auth->requireRole('editor');
            $allTags = [];
            $all = $app->storage->findAll('galleries');
            foreach ($all as $g) foreach ($g['tags'] ?? [] as $t) { if (!in_array($t, $allTags)) $allTags[] = $t; }
            sort($allTags);
            $app->renderAdmin('galleries/edit', ['gallery' => null, 'allTags' => $allTags]);
        });

        $this->router->get('/galleries/edit/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $gallery = $app->storage->find('galleries', $params['slug']);
            if (!$gallery) {
                ocms_flash_set('error', 'Galleria non trovata');
                ocms_redirect(ocms_base_url() . '/admin/galleries');
                return;
            }
            $allTags = [];
            $all = $app->storage->findAll('galleries');
            foreach ($all as $g) foreach ($g['tags'] ?? [] as $t) { if (!in_array($t, $allTags)) $allTags[] = $t; }
            sort($allTags);
            $app->renderAdmin('galleries/edit', ['gallery' => $gallery, 'allTags' => $allTags]);
        });

        $this->router->post('/galleries/save', function () use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/galleries');
                return;
            }

            $isNew = empty($_POST['original_slug']);
            $slug = ocms_slug($_POST['slug'] ?: $_POST['title']);
            $originalSlug = $_POST['original_slug'] ?? '';

            // If the slug changed, rename the directory and delete the old JSON file
            if (!$isNew && $originalSlug && $originalSlug !== $slug) {
                $oldDir = ocms_base_path() . '/uploads/gallery/' . $originalSlug;
                $newDir = ocms_base_path() . '/uploads/gallery/' . $slug;
                if (is_dir($oldDir) && !is_dir($newDir)) rename($oldDir, $newDir);
                $app->storage->delete('galleries', $originalSlug);
            }

            // Create gallery directory
            $galleryDir = ocms_base_path() . '/uploads/gallery/' . $slug;
            if (!is_dir($galleryDir)) mkdir($galleryDir, 0755, true);

            $existingGallery = !$isNew ? $app->storage->find('galleries', $originalSlug ?: $slug) : null;

            // Parse images from the hidden JSON field
            $images = json_decode($_POST['images'] ?? '[]', true) ?: [];

            // Tags
            $tags = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

            $gallery = [
                'id' => $_POST['id'] ?: ocms_uuid(),
                'title' => trim($_POST['title']),
                'slug' => $slug,
                'description' => trim($_POST['description'] ?? ''),
                'cover_image' => $_POST['cover_image'] ?? '',
                'images' => $images,
                'layout' => $_POST['layout'] ?? 'masonry',
                'tags' => $tags,
                'status' => $_POST['status'] ?? 'draft',
                'author' => $app->auth->user()['username'],
                'created_at' => $existingGallery['created_at'] ?? ocms_now(),
                'updated_at' => ocms_now(),
            ];

            $app->storage->save('galleries', $slug, $gallery);
            ocms_flash_set('success', $isNew ? 'Galleria creata!' : 'Galleria aggiornata!');
            ocms_redirect(ocms_base_url() . '/admin/galleries/edit/' . $slug);
        });

        $this->router->post('/galleries/delete/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            if (!ocms_csrf_verify()) {
                ocms_redirect(ocms_base_url() . '/admin/galleries');
                return;
            }
            // Delete the gallery image directory
            $dir = ocms_base_path() . '/uploads/gallery/' . basename($params['slug']);
            if (is_dir($dir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $f) {
                    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
                }
                rmdir($dir);
            }
            $app->storage->delete('galleries', $params['slug']);
            ocms_flash_set('success', 'Galleria eliminata');
            ocms_redirect(ocms_base_url() . '/admin/galleries');
        });

        // Upload images via AJAX
        $this->router->post('/galleries/upload-images/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $slug = basename($params['slug']);
            $dir = ocms_base_path() . '/uploads/gallery/' . $slug;
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'Nessun file caricato']);
                return;
            }

            $file = $_FILES['image'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mime, $allowedMimes)) {
                echo json_encode(['error' => 'Tipo file non consentito: ' . $mime]);
                return;
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['error' => 'File troppo grande (max 10MB)']);
                return;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
            $filename = $safeName . '_' . substr(uniqid(), -6) . '.' . $ext;

            $fullPath = $dir . '/' . $filename;
            $thumbPath = $dir . '/thumb_' . $filename;

            // Save and resize the uploaded image
            move_uploaded_file($file['tmp_name'], $fullPath);

            // Generate thumbnail (max 400px)
            if (function_exists('imagecreatefromjpeg')) {
                ocms_resize_cover($fullPath, $thumbPath, 400, 400);
            } else {
                copy($fullPath, $thumbPath);
            }

            $baseUrl = ocms_base_url() . '/uploads/gallery/' . $slug . '/';
            echo json_encode([
                'success' => true,
                'filename' => $filename,
                'url' => $baseUrl . $filename,
                'thumb_url' => $baseUrl . 'thumb_' . $filename,
                'original_name' => $file['name'],
            ]);
        });

        // Delete a single image via AJAX
        $this->router->post('/galleries/delete-image/{slug}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);
            $filename = basename($input['filename'] ?? '');
            if (!$filename) {
                echo json_encode(['error' => 'Filename mancante']);
                return;
            }

            $slug = basename($params['slug']);
            $dir = ocms_base_path() . '/uploads/gallery/' . $slug;
            @unlink($dir . '/' . $filename);
            @unlink($dir . '/thumb_' . $filename);

            echo json_encode(['success' => true]);
        });

        // ─── PACKAGE ───

        // Build a distributable package
        $this->router->post('/package/build', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            require ocms_base_path() . '/admin/views/package/build.php';
        });

        // ─── BACKUP ───

        // List available backups
        $this->router->get('/backup', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            $backupDir = ocms_data_path('backups');
            $backups = [];
            if (is_dir($backupDir)) {
                foreach (glob($backupDir . '/*.zip') as $f) {
                    $backups[] = ['name' => basename($f), 'size' => filesize($f), 'date' => date('c', filemtime($f))];
                }
                usort($backups, fn($a,$b) => strcmp($b['date'], $a['date']));
            }
            $app->renderAdmin('backup/index', ['backups' => $backups]);
        });

        // Data-only backup (fast)
        $this->router->post('/backup/create', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/backup'); }

            $backupDir = ocms_data_path('backups');
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            $name = 'backup-' . date('Y-m-d-His') . '.zip';
            $zipPath = $backupDir . '/' . $name;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                ocms_flash_set('error', 'Impossibile creare il backup');
                ocms_redirect(ocms_base_url() . '/admin/backup');
            }

            $dataDir = ocms_data_path();
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dataDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                $rel = substr($file->getPathname(), strlen($dataDir) + 1);
                if (str_starts_with($rel, 'backups/') || str_starts_with($rel, 'cache/')) continue;
                $zip->addFile($file->getPathname(), 'data/' . $rel);
            }

            $uploadsDir = ocms_base_path() . '/uploads';
            if (is_dir($uploadsDir)) {
                $iter2 = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iter2 as $file) {
                    $zip->addFile($file->getPathname(), 'uploads/' . substr($file->getPathname(), strlen($uploadsDir) + 1));
                }
            }

            $zip->close();
            ocms_flash_set('success', "Backup dati creato: {$name}");
            ocms_redirect(ocms_base_url() . '/admin/backup');
        });

        // Full backup (entire site + installer.php)
        $this->router->post('/backup/full', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/backup'); }

            $backupDir = ocms_data_path('backups');
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            $name = 'ocms-full-' . date('Y-m-d-His') . '.zip';
            $zipPath = $backupDir . '/' . $name;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                ocms_flash_set('error', 'Impossibile creare il backup');
                ocms_redirect(ocms_base_url() . '/admin/backup');
            }

            // Add the entire site (excluding backups and cache)
            $baseDir = ocms_base_path();
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                $rel = substr($file->getPathname(), strlen($baseDir) + 1);
                // Exclude backups, cache, and temporary files
                if (str_starts_with($rel, 'data/backups/') || str_starts_with($rel, 'data/cache/')) continue;
                if (str_ends_with($rel, '.tmp')) continue;
                $zip->addFile($file->getPathname(), 'ocms/' . $rel);
            }

            // Add installer.php at the ZIP root
            $installerPath = ocms_base_path() . '/core/installer-template.php';
            if (file_exists($installerPath)) {
                $zip->addFile($installerPath, 'installer.php');
            }

            $zip->close();
            ocms_flash_set('success', "Backup completo creato: {$name} (con installer)");
            ocms_redirect(ocms_base_url() . '/admin/backup');
        });

        // Distributable package (empty CMS + installer, no personal data)
        $this->router->post('/backup/distributable', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/backup'); return; }

            $backupDir = ocms_data_path('backups');
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            $name = 'ocms-dist-' . date('Y-m-d-His') . '.zip';
            $zipPath = $backupDir . '/' . $name;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                ocms_flash_set('error', 'Impossibile creare il pacchetto');
                ocms_redirect(ocms_base_url() . '/admin/backup');
                return;
            }

            $baseDir = ocms_base_path();
            $prefix = 'ocms/';
            $fileCount = 0;

            // Exclusions: user data, generated files, development artifacts
            $excludePatterns = [
                'data/', 'uploads/', 'install.php', 'sitemap.xml', 'robots.txt',
                'ARCHITECTURE.md', 'ROADMAP.md', 'admin/views/package/', 'ocms-latest.zip',
                'ocms-installer.php', '.git/', '.claude/',
            ];

            $shouldExclude = function(string $rel) use ($excludePatterns): bool {
                foreach ($excludePatterns as $pat) {
                    if ($rel === $pat || $rel === rtrim($pat, '/')) return true;
                    if (str_ends_with($pat, '/') && str_starts_with($rel, $pat)) return true;
                }
                return false;
            };

            // Recursively scan main directories
            $topDirs = ['core', 'admin', 'themes'];
            foreach ($topDirs as $dir) {
                $fullDir = $baseDir . '/' . $dir;
                if (!is_dir($fullDir)) continue;
                $zip->addEmptyDir($prefix . $dir);
                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($iter as $file) {
                    $rel = $dir . '/' . substr($file->getPathname(), strlen($fullDir) + 1);
                    if ($shouldExclude($rel)) continue;
                    $zip->addFile($file->getPathname(), $prefix . $rel);
                    $fileCount++;
                }
            }

            // Root files
            foreach (['.htaccess', 'index.php'] as $rootFile) {
                $full = $baseDir . '/' . $rootFile;
                if (file_exists($full)) {
                    $zip->addFile($full, $prefix . $rootFile);
                    $fileCount++;
                }
            }

            // Data: empty directory structure + base layout + .htaccess
            $zip->addEmptyDir($prefix . 'data');
            $zip->addFromString($prefix . 'data/.htaccess', "Deny from all\n");
            $fileCount++;

            $dataDirs = ['articles','analytics','backups','cache','categories','comments','forms',
                'lessons','logs','media','menus','pages','quiz-results','quizzes',
                'revisions','snippets','tags','translations','users','widgets'];
            foreach ($dataDirs as $d) {
                $zip->addEmptyDir($prefix . 'data/' . $d);
            }

            // Base layout
            $layoutFile = $baseDir . '/data/layouts/base.json';
            if (file_exists($layoutFile)) {
                $zip->addEmptyDir($prefix . 'data/layouts');
                $zip->addFile($layoutFile, $prefix . 'data/layouts/base.json');
                $fileCount++;
            }

            // Uploads: directory structure only
            foreach (['uploads','uploads/covers','uploads/documents','uploads/gallery',
                'uploads/images','uploads/media','uploads/lezioni'] as $d) {
                $zip->addEmptyDir($prefix . $d);
            }

            // Extensions and plugins
            $zip->addEmptyDir($prefix . 'extensions');

            // Clean default configuration
            $cleanConfig = json_encode([
                'site_name' => 'O-CMS',
                'site_description' => 'A lightweight flat-file CMS',
                'site_url' => '',
                'theme' => 'flavor',
                'language' => 'it',
                'timezone' => 'Europe/Rome',
                'date_format' => 'd/m/Y',
                'posts_per_page' => 10,
                'admin_email' => '',
                'maintenance_mode' => false,
                'registration_enabled' => false,
                'site_width_value' => '90',
                'site_width_unit' => '%',
                'smtp' => ['method'=>'php_mail','host'=>'','port'=>587,'encryption'=>'tls',
                    'username'=>'','password'=>'','from_email'=>'','from_name'=>''],
                'seo_global' => ['robots'=>"User-agent: *\nAllow: /",'head_scripts'=>'','body_scripts'=>''],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $zip->addFromString($prefix . 'data/config.json', $cleanConfig);
            $fileCount++;

            // Default menu
            $defaultMenu = json_encode([
                'name' => 'main', 'label' => 'Menu Principale',
                'items' => [
                    ['id'=>'menu-home','label'=>'Home','url'=>'/','target'=>'_self','icon'=>'','published'=>true,'children'=>[]],
                    ['id'=>'menu-blog','label'=>'Blog','url'=>'/blog','target'=>'_self','icon'=>'','published'=>true,'children'=>[]],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $zip->addFromString($prefix . 'data/menus/main.json', $defaultMenu);
            $fileCount++;

            // Default homepage
            $defaultHome = json_encode([
                'title'=>'Benvenuto','slug'=>'home',
                'content'=>'<h2>Il tuo nuovo sito è pronto</h2><p>Inizia a personalizzarlo dal <a href="/admin/">pannello di amministrazione</a>.</p>',
                'template'=>'home','status'=>'published','layout'=>'none',
                'meta'=>['title'=>'','description'=>'','og_image'=>''],
                'order'=>0,'parent'=>null,'author'=>'admin',
                'created_at'=>date('c'),'updated_at'=>date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $zip->addFromString($prefix . 'data/pages/home.json', $defaultHome);
            $fileCount++;

            // Installer at the ZIP root
            $installerPath = $baseDir . '/core/installer-template.php';
            if (file_exists($installerPath)) {
                $zip->addFile($installerPath, 'installer.php');
                $fileCount++;
            }

            $zip->close();
            $sizeKb = round(filesize($zipPath) / 1024);
            ocms_flash_set('success', "Pacchetto distribuibile creato: {$name} ({$fileCount} file, {$sizeKb} KB)");
            ocms_redirect(ocms_base_url() . '/admin/backup');
        });

        $this->router->get('/backup/download/{name}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            $file = ocms_data_path('backups') . '/' . basename($params['name']);
            if (!file_exists($file)) { ocms_flash_set('error', 'File non trovato'); ocms_redirect(ocms_base_url() . '/admin/backup'); }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        });

        $this->router->post('/backup/delete/{name}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/backup'); }
            $file = ocms_data_path('backups') . '/' . basename($params['name']);
            if (file_exists($file)) unlink($file);
            ocms_flash_set('success', 'Backup eliminato');
            ocms_redirect(ocms_base_url() . '/admin/backup');
        });

        $this->router->post('/backup/restore/{name}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/backup'); }
            $file = ocms_data_path('backups') . '/' . basename($params['name']);
            if (!file_exists($file)) { ocms_flash_set('error', 'File non trovato'); ocms_redirect(ocms_base_url() . '/admin/backup'); }

            $zip = new ZipArchive();
            if ($zip->open($file) !== true) { ocms_flash_set('error', 'ZIP non valido'); ocms_redirect(ocms_base_url() . '/admin/backup'); }

            // Safe extraction with path traversal prevention
            $destBase = ocms_base_path();
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_contains($name, '..') || str_starts_with($name, '/')) continue;
                if (str_ends_with($name, '/')) continue;
                $target = $destBase . '/' . $name;
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                file_put_contents($target, $zip->getFromIndex($i));
            }
            $zip->close();

            ocms_flash_set('success', 'Backup ripristinato!');
            ocms_redirect(ocms_base_url() . '/admin/backup');
        });

        // ─── SEARCH ───

        // Quick AJAX search (sidebar)
        $this->router->get('/search', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');
            $q = trim($_GET['q'] ?? '');
            $engine = new SearchEngine($app);
            $result = $engine->quickSearch($q);
            // Format results for the sidebar
            $formatted = array_map(fn($r) => [
                'type' => $r['type'],
                'title' => $r['highlights']['title'] ?? $r['title'],
                'url' => $r['admin_url'] ?? '#',
                'icon' => $r['icon'] ?? '',
                'type_label' => $r['type_label'] ?? $r['type'],
                'score' => $r['score'],
            ], $result['results']);
            echo json_encode(['results' => $formatted, 'query' => $q, 'total' => $result['total']]);
        });

        // Advanced search page
        $this->router->get('/search/full', function () use ($app) {
            $app->auth->requireRole('editor');
            $engine = new SearchEngine($app);
            $q = trim($_GET['q'] ?? '');
            $types = !empty($_GET['types']) ? (array)$_GET['types'] : [];
            $status = $_GET['status'] ?? '';
            $category = $_GET['category'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 20;

            $result = [];
            if ($q) {
                $result = $engine->search($q, [
                    'types' => $types,
                    'status' => $status,
                    'category' => $category,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'limit' => $perPage,
                    'offset' => ($page - 1) * $perPage,
                ]);
            }

            $filters = $engine->getFilterOptions();
            $suggestions = $q ? $engine->suggest($q) : [];

            $app->renderAdmin('search/index', [
                'query' => $q,
                'result' => $result,
                'filters' => $filters,
                'suggestions' => $suggestions,
                'activeTypes' => $types,
                'activeStatus' => $status,
                'activeCategory' => $category,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'page' => $page,
                'perPage' => $perPage,
            ]);
        });

        // AJAX search suggestions
        $this->router->get('/search/suggest', function () use ($app) {
            $app->auth->requireRole('editor');
            header('Content-Type: application/json');
            $engine = new SearchEngine($app);
            echo json_encode($engine->suggest(trim($_GET['q'] ?? '')));
        });

        // ─── REST API ───

        // --- Read endpoints (registered+) ---

        $this->router->get('/api/pages', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            echo json_encode($app->storage->findAll('pages', fn($p) => $p['status'] === 'published'));
        });

        $this->router->get('/api/pages/{slug}', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            $page = $app->storage->find('pages', $params['slug']);
            if (!$page || $page['status'] !== 'published') { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            echo json_encode($page);
        });

        $this->router->get('/api/articles', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            echo json_encode($app->storage->findAll('articles', function ($a) {
                if ($a['status'] !== 'published') return false;
                if (!empty($a['publish_at']) && $a['publish_at'] > date('c')) return false;
                return true;
            }));
        });

        $this->router->get('/api/articles/{slug}', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            $article = $app->storage->find('articles', $params['slug']);
            if (!$article || $article['status'] !== 'published') { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            if (!empty($article['publish_at']) && $article['publish_at'] > date('c')) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            echo json_encode($article);
        });

        $this->router->get('/api/categories', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            echo json_encode($app->storage->findAll('categories', null, 'name', 'asc'));
        });

        $this->router->get('/api/menus/{name}', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            $menu = $app->storage->find('menus', $params['name']);
            if (!$menu) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            echo json_encode($menu);
        });

        $this->router->get('/api/media', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            echo json_encode($app->storage->findAll('media', null, 'uploaded_at', 'desc'));
        });

        $this->router->get('/api/config', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'registered');
            header('Content-Type: application/json');
            $safe = $app->config;
            unset($safe['seo_global'], $safe['smtp']);
            echo json_encode($safe);
        });

        // --- Articles CRUD (publisher+) ---

        $this->router->post('/api/articles', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'publisher');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Campo title obbligatorio']);
                return;
            }
            $slug = ocms_slug($input['slug'] ?? $input['title']);
            $publishAt = '';
            if (!empty($input['publish_at'])) {
                $publishAt = (new DateTime($input['publish_at']))->format('c');
            }
            $article = [
                'id' => ocms_uuid(),
                'title' => trim($input['title']),
                'slug' => $slug,
                'excerpt' => trim($input['excerpt'] ?? ''),
                'content' => $input['content'] ?? '',
                'cover_image' => trim($input['cover_image'] ?? ''),
                'gallery' => $input['gallery'] ?? [],
                'category' => $input['category'] ?? '',
                'tags' => $input['tags'] ?? [],
                'status' => $input['status'] ?? 'draft',
                'featured' => !empty($input['featured']),
                'publish_at' => $publishAt,
                'meta' => $input['meta'] ?? ['title' => '', 'description' => '', 'og_image' => ''],
                'author' => $user['username'],
                'created_at' => ocms_now(),
                'updated_at' => ocms_now(),
            ];
            $app->storage->save('articles', $slug, $article);
            http_response_code(201);
            echo json_encode(['success' => true, 'slug' => $slug, 'article' => $article]);
        });

        $this->router->post('/api/articles/{slug}', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'publisher');
            header('Content-Type: application/json');
            $article = $app->storage->find('articles', $params['slug']);
            if (!$article) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            // Publishers can only edit their own articles; editor+ can edit all
            if (!$this->apiHasRole($user, 'editor') && ($article['author'] ?? '') !== $user['username']) {
                http_response_code(403);
                echo json_encode(['error' => 'Puoi modificare solo i tuoi articoli']);
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            foreach (['title', 'excerpt', 'content', 'cover_image', 'gallery', 'category', 'tags', 'status', 'featured', 'meta'] as $field) {
                if (isset($input[$field])) $article[$field] = $input[$field];
            }
            if (isset($input['publish_at'])) {
                $article['publish_at'] = !empty($input['publish_at']) ? (new DateTime($input['publish_at']))->format('c') : '';
            }
            if (isset($input['title']) && !empty($input['slug'])) {
                $newSlug = ocms_slug($input['slug']);
                if ($newSlug !== $params['slug']) {
                    $app->storage->delete('articles', $params['slug']);
                    $article['slug'] = $newSlug;
                }
            }
            $article['updated_at'] = ocms_now();
            $app->storage->save('articles', $article['slug'], $article);
            echo json_encode(['success' => true, 'article' => $article]);
        });

        $this->router->post('/api/articles/{slug}/delete', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'publisher');
            header('Content-Type: application/json');
            $article = $app->storage->find('articles', $params['slug']);
            if (!$article) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            if (!$this->apiHasRole($user, 'editor') && ($article['author'] ?? '') !== $user['username']) {
                http_response_code(403);
                echo json_encode(['error' => 'Puoi eliminare solo i tuoi articoli']);
                return;
            }
            $app->storage->delete('articles', $params['slug']);
            echo json_encode(['success' => true]);
        });

        // --- Pages CRUD (editor+) ---

        $this->router->post('/api/pages', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['title'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Campo title obbligatorio']);
                return;
            }
            $slug = ocms_slug($input['slug'] ?? $input['title']);
            $page = [
                'id' => ocms_uuid(),
                'title' => trim($input['title']),
                'slug' => $slug,
                'content' => $input['content'] ?? '',
                'template' => $input['template'] ?? 'default',
                'layout' => $input['layout'] ?? 'none',
                'status' => $input['status'] ?? 'draft',
                'meta' => $input['meta'] ?? ['title' => '', 'description' => '', 'og_image' => ''],
                'order' => (int)($input['order'] ?? 0),
                'parent' => $input['parent'] ?? null,
                'author' => $user['username'],
                'created_at' => ocms_now(),
                'updated_at' => ocms_now(),
            ];
            $app->storage->save('pages', $slug, $page);
            http_response_code(201);
            echo json_encode(['success' => true, 'slug' => $slug, 'page' => $page]);
        });

        $this->router->post('/api/pages/{slug}', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            $page = $app->storage->find('pages', $params['slug']);
            if (!$page) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            $input = json_decode(file_get_contents('php://input'), true);
            foreach (['title', 'content', 'template', 'layout', 'status', 'meta', 'order', 'parent'] as $field) {
                if (isset($input[$field])) $page[$field] = $input[$field];
            }
            if (!empty($input['slug'])) {
                $newSlug = ocms_slug($input['slug']);
                if ($newSlug !== $params['slug']) {
                    $app->storage->delete('pages', $params['slug']);
                    $page['slug'] = $newSlug;
                }
            }
            $page['updated_at'] = ocms_now();
            $app->storage->save('pages', $page['slug'], $page);
            echo json_encode(['success' => true, 'page' => $page]);
        });

        $this->router->post('/api/pages/{slug}/delete', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            if (!$app->storage->exists('pages', $params['slug'])) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            $app->storage->delete('pages', $params['slug']);
            echo json_encode(['success' => true]);
        });

        // --- Categories CRUD (editor+) ---

        $this->router->post('/api/categories', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Campo name obbligatorio']);
                return;
            }
            $slug = ocms_slug($input['name']);
            $cat = [
                'name' => trim($input['name']),
                'slug' => $slug,
                'description' => trim($input['description'] ?? ''),
                'parent' => $input['parent'] ?? null,
                'created_at' => ocms_now(),
            ];
            $app->storage->save('categories', $slug, $cat);
            http_response_code(201);
            echo json_encode(['success' => true, 'slug' => $slug, 'category' => $cat]);
        });

        $this->router->post('/api/categories/{slug}/delete', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            if (!$app->storage->exists('categories', $params['slug'])) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            $app->storage->delete('categories', $params['slug']);
            echo json_encode(['success' => true]);
        });

        // --- Comments API (editor+) ---

        $this->router->get('/api/comments', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            $status = $_GET['status'] ?? 'all';
            $articleSlug = $_GET['article'] ?? '';
            $comments = $app->storage->findAll('comments', function ($c) use ($status, $articleSlug) {
                if ($status !== 'all' && ($c['status'] ?? '') !== $status) return false;
                if ($articleSlug && ($c['article_slug'] ?? '') !== $articleSlug) return false;
                return true;
            }, 'created_at', 'desc');
            echo json_encode($comments);
        });

        $this->router->post('/api/comments/{id}/approve', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            $comment = $app->storage->find('comments', $params['id']);
            if (!$comment) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            $comment['status'] = 'approved';
            $app->storage->save('comments', $params['id'], $comment);
            echo json_encode(['success' => true, 'comment' => $comment]);
        });

        $this->router->post('/api/comments/{id}/reject', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'editor');
            header('Content-Type: application/json');
            $comment = $app->storage->find('comments', $params['id']);
            if (!$comment) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            $comment['status'] = 'rejected';
            $app->storage->save('comments', $params['id'], $comment);
            echo json_encode(['success' => true, 'comment' => $comment]);
        });

        $this->router->post('/api/comments/{id}/delete', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'administrator');
            header('Content-Type: application/json');
            if (!$app->storage->exists('comments', $params['id'])) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            $app->storage->delete('comments', $params['id']);
            echo json_encode(['success' => true]);
        });

        // --- Analytics API (administrator+) ---

        $this->router->get('/api/analytics', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'administrator');
            header('Content-Type: application/json');
            $days = (int)($_GET['days'] ?? 30);
            if ($days < 1) $days = 1;
            if ($days > 365) $days = 365;
            echo json_encode(ocms_analytics_range($days));
        });

        // --- Users (administrator+) ---

        $this->router->get('/api/users', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'administrator');
            header('Content-Type: application/json');
            $users = $app->storage->findAll('users');
            // Remove sensitive fields (password and tokens)
            $safe = array_map(function ($u) {
                unset($u['password'], $u['api_tokens'], $u['activation_token']);
                return $u;
            }, $users);
            echo json_encode(array_values($safe));
        });

        $this->router->get('/api/users/{username}', function ($params) use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'administrator');
            header('Content-Type: application/json');
            $u = $app->storage->find('users', $params['username']);
            if (!$u) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
            unset($u['password'], $u['api_tokens'], $u['activation_token']);
            echo json_encode($u);
        });

        // --- Settings (super_administrator) ---

        $this->router->get('/api/settings', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'super_administrator');
            header('Content-Type: application/json');
            $config = $app->storage->readFile('config.json') ?? [];
            unset($config['smtp']['password']);
            echo json_encode($config);
        });

        $this->router->post('/api/settings', function () use ($app) {
            $user = $this->apiAuth($app);
            $this->apiRequireRole($user, 'super_administrator');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { http_response_code(400); echo json_encode(['error' => 'JSON body richiesto']); return; }
            $config = $app->storage->readFile('config.json') ?? [];
            $allowed = ['site_name', 'site_description', 'language', 'timezone', 'date_format', 'posts_per_page', 'admin_email', 'maintenance_mode', 'registration_enabled'];
            foreach ($allowed as $key) {
                if (isset($input[$key])) $config[$key] = $input[$key];
            }
            $app->storage->writeFile('config.json', $config);
            echo json_encode(['success' => true, 'config' => $config]);
        });

        // ─── API MANAGEMENT ───

        // API documentation and token management page
        $this->router->get('/api-docs', function () use ($app) {
            $app->auth->requireRole('registered');
            $currentUser = $app->auth->user()['username'];
            $tokens = $app->auth->getApiTokens($currentUser);
            $app->renderAdmin('api/index', ['tokens' => $tokens]);
        });

        // Create API token
        $this->router->post('/api-tokens/create', function () use ($app) {
            $app->auth->requireRole('registered');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }
            $name = trim($input['name'] ?? '');
            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Nome token obbligatorio']);
                return;
            }
            $username = $app->auth->user()['username'];
            $entry = $app->auth->createApiToken($username, $name);
            if (!$entry) {
                http_response_code(500);
                echo json_encode(['error' => 'Errore nella creazione del token']);
                return;
            }
            echo json_encode(['success' => true, 'token' => $entry['token'], 'id' => $entry['id'], 'name' => $entry['name']]);
        });

        // Revoke API token
        $this->router->post('/api-tokens/revoke', function () use ($app) {
            $app->auth->requireRole('registered');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            $csrfToken = $input['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $csrfToken)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }
            $tokenId = $input['token_id'] ?? '';
            if (!$tokenId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID token obbligatorio']);
                return;
            }
            $username = $app->auth->user()['username'];
            $app->auth->revokeApiToken($username, $tokenId);
            echo json_encode(['success' => true]);
        });

        // ─── GUIDE ───

        // Render the admin guide page
        $this->router->get('/guide', function () use ($app) {
            $app->auth->requireRole('registered');
            $app->renderAdmin('guide');
        });

        // ─── LAYOUT BUILDER ───

        // List all layouts
        $this->router->get('/layouts', function () use ($app) {
            $app->auth->requireRole('administrator');
            $layouts = $app->storage->findAll('layouts', null, 'name', 'asc');
            $app->renderAdmin('layouts/index', ['layouts' => $layouts]);
        });

        $this->router->get('/layouts/edit/{id}', function ($params) use ($app) {
            $app->auth->requireRole('administrator');
            $layout = $app->storage->find('layouts', $params['id']);
            if (!$layout) {
                ocms_flash_set('error', 'Layout non trovato');
                ocms_redirect(ocms_base_url() . '/admin/layouts');
            }
            $media = $app->storage->findAll('media', null, 'uploaded_at', 'desc');
            $app->renderAdmin('layouts/edit', ['layout' => $layout, 'media' => $media]);
        });

        $this->router->post('/layouts/save', function () use ($app) {
            $app->auth->requireRole('administrator');
            header('Content-Type: application/json');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403); echo json_encode(['error' => 'CSRF']); return;
            }
            $id = basename($input['id'] ?? 'base');
            $layout = [
                'id' => $id,
                'name' => $input['name'] ?? $id,
                'description' => $input['description'] ?? '',
                'sections' => $input['sections'] ?? [],
            ];
            $app->storage->save('layouts', $id, $layout);
            echo json_encode(['success' => true]);
        });

        $this->router->post('/layouts/create', function () use ($app) {
            $app->auth->requireRole('administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/layouts'); }
            $name = trim($_POST['name'] ?? '');
            if (!$name) { ocms_flash_set('error', 'Nome obbligatorio'); ocms_redirect(ocms_base_url() . '/admin/layouts'); }
            $id = ocms_slug($name);

            // Copy from the base layout
            $base = $app->storage->find('layouts', 'base');
            $layout = $base ?: ['sections' => []];
            $layout['id'] = $id;
            $layout['name'] = $name;
            $layout['description'] = trim($_POST['description'] ?? '');
            $app->storage->save('layouts', $id, $layout);
            ocms_flash_set('success', "Layout '{$name}' creato");
            ocms_redirect(ocms_base_url() . '/admin/layouts/edit/' . $id);
        });

        $this->router->post('/layouts/delete/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/layouts'); }
            if ($params['id'] === 'base') { ocms_flash_set('error', 'Non puoi eliminare il layout base'); ocms_redirect(ocms_base_url() . '/admin/layouts'); }
            $app->storage->delete('layouts', $params['id']);
            ocms_flash_set('success', 'Layout eliminato');
            ocms_redirect(ocms_base_url() . '/admin/layouts');
        });

        // Preview layout (renders HTML)
        $this->router->post('/layouts/preview', function () use ($app) {
            $app->auth->requireRole('administrator');
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($_SESSION['_csrf_token']) || empty($input['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $input['_csrf_token'])) {
                http_response_code(403); echo 'CSRF'; return;
            }
            $renderer = new LayoutRenderer($app, ['title' => 'Anteprima', 'content' => '<p>Contenuto della pagina di esempio.</p>']);
            echo $renderer->render(['sections' => $input['sections'] ?? []]);
        });

        // ─── THEMES ───

        // List all installed themes
        $this->router->get('/themes', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            $themesDir = ocms_base_path() . '/themes';
            $themes = [];
            foreach (scandir($themesDir) as $d) {
                if ($d === '.' || $d === '..' || !is_dir($themesDir . '/' . $d)) continue;
                $manifest = $themesDir . '/' . $d . '/theme.json';
                $meta = file_exists($manifest) ? json_decode(file_get_contents($manifest), true) : [];
                $meta['id'] = $d;
                $meta['active'] = ($app->config['theme'] ?? 'flavor') === $d;
                // Count files in the theme
                $count = 0;
                $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themesDir.'/'.$d, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iter as $f) { if ($f->isFile()) $count++; }
                $meta['file_count'] = $count;
                // Check for screenshot
                $meta['has_screenshot'] = file_exists($themesDir . '/' . $d . '/screenshot.png') || file_exists($themesDir . '/' . $d . '/screenshot.jpg');
                $themes[] = $meta;
            }
            $app->renderAdmin('themes/index', ['themes' => $themes]);
        });

        // Theme creation wizard (form)
        $this->router->get('/themes/wizard', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            $app->renderAdmin('themes/wizard');
        });

        // Theme creation wizard (process)
        $this->router->post('/themes/wizard', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/themes/wizard'); }

            $id = ocms_slug($_POST['name'] ?? 'my-theme');
            $themesDir = ocms_base_path() . '/themes/' . $id;

            if (is_dir($themesDir)) {
                ocms_flash_set('error', "Il tema '{$id}' esiste già");
                ocms_redirect(ocms_base_url() . '/admin/themes/wizard');
            }

            // Create directory structure
            mkdir($themesDir . '/assets/css', 0755, true);
            mkdir($themesDir . '/assets/js', 0755, true);
            mkdir($themesDir . '/assets/img', 0755, true);
            mkdir($themesDir . '/layouts', 0755, true);
            mkdir($themesDir . '/templates', 0755, true);

            $colors = [
                'primary' => $_POST['color_primary'] ?? '#6366f1',
                'secondary' => $_POST['color_secondary'] ?? '#a78bfa',
                'bg' => $_POST['color_bg'] ?? '#0f172a',
                'text' => $_POST['color_text'] ?? '#f1f5f9',
            ];
            $font = $_POST['font'] ?? 'Inter';
            $layout = $_POST['layout_style'] ?? 'modern';

            // theme.json
            $manifest = [
                'name' => trim($_POST['name']),
                'version' => '1.0.0',
                'author' => trim($_POST['author'] ?? ''),
                'author_url' => trim($_POST['author_url'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'license' => $_POST['license'] ?? 'MIT',
                'colors' => $colors,
                'font' => $font,
                'layout' => $layout,
            ];
            file_put_contents($themesDir . '/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            // Generate CSS
            $css = self::generateThemeCSS($manifest);
            file_put_contents($themesDir . '/assets/css/style.css', $css);

            // Generate templates
            $templates = ['home', 'page', 'blog', 'article', '404'];
            foreach ($templates as $tpl) {
                $content = self::generateThemeTemplate($tpl, $manifest);
                file_put_contents($themesDir . '/templates/' . $tpl . '.php', $content);
            }

            // JS placeholder file
            file_put_contents($themesDir . '/assets/js/app.js', "/* {$manifest['name']} — JavaScript */\ndocument.addEventListener('DOMContentLoaded', function() {\n    // Il tuo codice qui\n});\n");

            // README
            file_put_contents($themesDir . '/README.md', self::generateThemeReadme($manifest));

            ocms_flash_set('success', "Tema '{$manifest['name']}' creato! Puoi ora attivarlo o modificarlo.");
            ocms_redirect(ocms_base_url() . '/admin/themes');
        });

        // Activate theme
        $this->router->post('/themes/activate/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/themes'); }
            $id = basename($params['id']);
            $themesDir = ocms_base_path() . '/themes/' . $id;
            if (!is_dir($themesDir)) { ocms_flash_set('error', 'Tema non trovato'); ocms_redirect(ocms_base_url() . '/admin/themes'); }

            $config = $app->storage->readFile('config.json') ?? [];
            $config['theme'] = $id;
            $app->storage->writeFile('config.json', $config);
            ocms_flash_set('success', "Tema '{$id}' attivato!");
            ocms_redirect(ocms_base_url() . '/admin/themes');
        });

        // Download theme as ZIP
        $this->router->get('/themes/download/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            $id = basename($params['id']);
            $themeDir = ocms_base_path() . '/themes/' . $id;
            if (!is_dir($themeDir)) { ocms_flash_set('error', 'Tema non trovato'); ocms_redirect(ocms_base_url() . '/admin/themes'); }

            $zipPath = ocms_data_path('backups') . '/' . $id . '-theme.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                ocms_flash_set('error', 'Impossibile creare ZIP'); ocms_redirect(ocms_base_url() . '/admin/themes');
            }
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($themeDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iter as $file) {
                $zip->addFile($file->getPathname(), $id . '/' . substr($file->getPathname(), strlen($themeDir) + 1));
            }
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $id . '-theme.zip"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            unlink($zipPath);
            exit;
        });

        // Install theme from ZIP upload
        $this->router->post('/themes/install', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/themes'); }

            if (empty($_FILES['package']) || $_FILES['package']['error'] !== UPLOAD_ERR_OK) {
                ocms_flash_set('error', 'Nessun file o errore upload'); ocms_redirect(ocms_base_url() . '/admin/themes');
            }

            $zip = new ZipArchive();
            if ($zip->open($_FILES['package']['tmp_name']) !== true) {
                ocms_flash_set('error', 'ZIP non valido'); ocms_redirect(ocms_base_url() . '/admin/themes');
            }

            // Look for theme.json inside the ZIP
            $themeId = null;
            $prefix = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (basename($name) === 'theme.json') {
                    $dir = dirname($name);
                    $prefix = ($dir === '.') ? '' : $dir . '/';
                    $themeId = $prefix ? basename($dir) : null;
                    break;
                }
            }

            if (!$themeId) {
                // Try to use the first folder name
                $first = $zip->getNameIndex(0);
                if (str_contains($first, '/')) $themeId = explode('/', $first)[0];
            }

            if (!$themeId) {
                $zip->close();
                ocms_flash_set('error', 'Impossibile determinare il nome del tema dal ZIP');
                ocms_redirect(ocms_base_url() . '/admin/themes');
            }

            $destDir = ocms_base_path() . '/themes/' . basename($themeId);
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $rel = $prefix ? substr($name, strlen($prefix)) : $name;
                if (!$rel || str_ends_with($name, '/') || str_contains($rel, '..')) continue;
                $target = $destDir . '/' . $rel;
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                file_put_contents($target, $zip->getFromIndex($i));
            }
            $zip->close();

            ocms_flash_set('success', "Tema '{$themeId}' installato!");
            ocms_redirect(ocms_base_url() . '/admin/themes');
        });

        // Delete theme
        $this->router->post('/themes/delete/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) { ocms_flash_set('error', 'CSRF'); ocms_redirect(ocms_base_url() . '/admin/themes'); }
            $id = basename($params['id']);
            if ($id === ($app->config['theme'] ?? 'flavor')) {
                ocms_flash_set('error', 'Non puoi eliminare il tema attivo'); ocms_redirect(ocms_base_url() . '/admin/themes');
            }
            if ($id === 'flavor') {
                ocms_flash_set('error', 'Non puoi eliminare il tema di default'); ocms_redirect(ocms_base_url() . '/admin/themes');
            }
            $dir = ocms_base_path() . '/themes/' . $id;
            if (is_dir($dir)) {
                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iter as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
                rmdir($dir);
            }
            ocms_flash_set('success', 'Tema eliminato');
            ocms_redirect(ocms_base_url() . '/admin/themes');
        });

        // ─── EXTENSIONS ───

        // List all extensions
        $this->router->get('/extensions', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            $extensions = $app->extensions->getAll();
            $app->renderAdmin('extensions/index', ['extensions' => $extensions]);
        });

        // New extension wizard -- form
        $this->router->get('/extensions/wizard', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            $app->renderAdmin('extensions/wizard');
        });

        // New extension wizard -- creation
        $this->router->post('/extensions/wizard', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/extensions/wizard');
            }

            $result = $app->extensions->createFromWizard([
                'name' => trim($_POST['name'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'author' => trim($_POST['author'] ?? ''),
                'author_url' => trim($_POST['author_url'] ?? ''),
                'license' => trim($_POST['license'] ?? 'MIT'),
                'icon' => trim($_POST['icon'] ?? 'puzzle'),
                'has_admin' => isset($_POST['has_admin']),
                'has_frontend' => isset($_POST['has_frontend']),
                'has_assets' => isset($_POST['has_assets']),
                'has_data' => isset($_POST['has_data']),
                'permissions' => array_filter(array_map('trim', explode(',', $_POST['permissions'] ?? ''))),
            ]);

            if ($result['success']) {
                ocms_flash_set('success', "Estensione '{$result['id']}' creata! Ora puoi modificare i file in extensions/{$result['id']}/");
                ocms_redirect(ocms_base_url() . '/admin/extensions/detail/' . $result['id']);
            } else {
                ocms_flash_set('error', $result['error']);
                ocms_redirect(ocms_base_url() . '/admin/extensions/wizard');
            }
        });

        // Extension detail page
        $this->router->get('/extensions/detail/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            $manifest = $app->extensions->getManifest($params['id']);
            if (!$manifest) {
                ocms_flash_set('error', 'Estensione non trovata');
                ocms_redirect(ocms_base_url() . '/admin/extensions');
            }

            // Read the file structure
            $extDir = ocms_base_path() . '/extensions/' . $params['id'];
            $files = [];
            if (is_dir($extDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $file) {
                    $relativePath = substr($file->getPathname(), strlen($extDir) + 1);
                    $files[] = [
                        'path' => $relativePath,
                        'is_dir' => $file->isDir(),
                        'size' => $file->isFile() ? $file->getSize() : 0,
                    ];
                }
                sort($files);
            }

            $app->renderAdmin('extensions/detail', [
                'ext' => $manifest,
                'files' => $files,
            ]);
        });

        // Enable/Disable extension (AJAX)
        $this->router->post('/extensions/toggle/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['_csrf_token'] ?? '';
            if (!hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF non valido']);
                return;
            }

            $manifest = $app->extensions->getManifest($params['id']);
            if (!$manifest) {
                http_response_code(404);
                echo json_encode(['error' => 'Estensione non trovata']);
                return;
            }

            $enabled = !($manifest['enabled'] ?? false);
            if ($enabled) {
                $app->extensions->enable($params['id']);
            } else {
                $app->extensions->disable($params['id']);
            }

            echo json_encode(['success' => true, 'enabled' => $enabled]);
        });

        // Upload and install extension from ZIP
        $this->router->post('/extensions/install', function () use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/extensions');
            }

            if (empty($_FILES['package']) || $_FILES['package']['error'] !== UPLOAD_ERR_OK) {
                ocms_flash_set('error', 'Nessun file caricato o errore upload');
                ocms_redirect(ocms_base_url() . '/admin/extensions');
            }

            $tmpPath = $_FILES['package']['tmp_name'];
            $result = $app->extensions->installFromZip($tmpPath);

            if ($result['success']) {
                ocms_flash_set('success', "Estensione '{$result['id']}' installata con successo!");
                ocms_redirect(ocms_base_url() . '/admin/extensions/detail/' . $result['id']);
            } else {
                ocms_flash_set('error', $result['error']);
                ocms_redirect(ocms_base_url() . '/admin/extensions');
            }
        });

        // Uninstall extension
        $this->router->post('/extensions/uninstall/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            if (!ocms_csrf_verify()) {
                ocms_flash_set('error', 'Token di sicurezza non valido');
                ocms_redirect(ocms_base_url() . '/admin/extensions');
            }

            $app->extensions->uninstall($params['id']);
            ocms_flash_set('success', 'Estensione disinstallata');
            ocms_redirect(ocms_base_url() . '/admin/extensions');
        });

        // Download extension as ZIP package
        $this->router->get('/extensions/download/{id}', function ($params) use ($app) {
            $app->auth->requireRole('super_administrator');
            $zipPath = $app->extensions->createPackage($params['id']);
            if (!$zipPath || !file_exists($zipPath)) {
                ocms_flash_set('error', 'Impossibile creare il pacchetto');
                ocms_redirect(ocms_base_url() . '/admin/extensions');
                return;
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            unlink($zipPath);
            exit;
        });

        // ─── API ───

        // Generic resource listing API (used via AJAX)
        $this->router->get('/api/{resource}', function ($params) use ($app) {
            $app->auth->requireRole('editor');
            $allowed = ['pages', 'articles', 'categories', 'tags', 'menus', 'users'];
            $resource = $params['resource'];
            if (!in_array($resource, $allowed, true)) {
                http_response_code(404);
                echo json_encode(['error' => 'Risorsa non trovata']);
                return;
            }
            header('Content-Type: application/json');
            echo json_encode($app->storage->findAll($resource));
        });
    }

    /**
     * Render a template from the active theme.
     *
     * Falls back to the default 'flavor' theme if the template is not found
     * in the currently active theme. Injects the CSS --site-width variable
     * immediately after the <head> tag.
     *
     * @param string $template Template name (without .php extension)
     * @param array  $data     Variables to extract into the template scope
     * @return void
     */
    public function render(string $template, array $data = []): void {
        $theme = $this->config['theme'] ?? 'flavor';
        $file = ocms_base_path() . "/themes/{$theme}/templates/{$template}.php";

        if (!file_exists($file)) {
            $file = ocms_base_path() . "/themes/flavor/templates/{$template}.php";
        }

        if (!file_exists($file)) {
            echo "Template '{$template}' non trovato";
            return;
        }

        $app = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $html = ob_get_clean();
        // Inject the CSS --site-width variable right after <head>
        $widthStyle = ocms_site_width_style();
        $html = preg_replace('/(<head[^>]*>)/i', '$1' . "\n    " . $widthStyle, $html, 1);
        // Inject powered-by badge before </body>
        $html = $this->injectPoweredBy($html);
        echo $html;
    }

    /**
     * Render a page using the Layout Builder.
     *
     * How it works:
     * 1. Loads the base layout (inherited by all pages)
     * 2. Loads the page-specific layout (if different from base)
     * 3. Merge: the specific layout overrides base sections with the same ID
     * 4. The "content" module inserts the page's HTML content
     * 5. Outputs the full HTML document with head, theme CSS, and body
     *
     * @param array $page Page data array (must include 'layout', 'title', 'content', 'meta')
     * @return void
     */
    public function renderWithLayout(array $page): void {
        $layoutId = $page['layout'] ?? 'base';
        $theme = $this->config['theme'] ?? 'flavor';
        $font = 'Inter';

        // Load theme.json for the font setting
        $themeJson = ocms_base_path() . "/themes/{$theme}/theme.json";
        if (file_exists($themeJson)) {
            $themeMeta = json_decode(file_get_contents($themeJson), true);
            $font = $themeMeta['font'] ?? 'Inter';
        }

        // Load the base layout
        $baseLayout = $this->storage->find('layouts', 'base');

        // If the chosen layout is not "base", load and merge it
        $layout = $baseLayout ?? ['sections' => []];
        if ($layoutId !== 'base') {
            $specificLayout = $this->storage->find('layouts', $layoutId);
            if ($specificLayout) {
                $layout = LayoutRenderer::mergeLayouts($layout, $specificLayout);
            }
        }

        // Render the layout
        $renderer = new LayoutRenderer($this, $page);
        $bodyHtml = $renderer->render($layout);

        // Output the full HTML document
        $siteName = ocms_escape($this->config['site_name'] ?? 'O-CMS');
        $pageTitle = ocms_escape($page['meta']['title'] ?? $page['title'] ?? $siteName);
        $metaDesc = ocms_escape($page['meta']['description'] ?? '');
        $baseUrl = ocms_base_url();
        $fontUrl = urlencode($font);
        $cssUrl = "{$baseUrl}/themes/{$theme}/assets/css/style.css";
        $lang = $this->config['language'] ?? 'it';
        $siteWidthStyle = ocms_site_width_style();

        $layoutHtml = <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle} — {$siteName}</title>
    <meta name="description" content="{$metaDesc}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family={$fontUrl}:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{$cssUrl}">
    {$siteWidthStyle}
</head>
<body>
{$bodyHtml}
</body>
</html>
HTML;
        echo $this->injectPoweredBy($layoutHtml);
    }

    /**
     * Render an admin panel view.
     *
     * @param string $view View path relative to admin/views/ (without .php extension)
     * @param array  $data Variables to extract into the view scope
     * @return void
     */
    public function renderAdmin(string $view, array $data = []): void {
        $file = ocms_base_path() . "/admin/views/{$view}.php";

        if (!file_exists($file)) {
            echo "Vista admin '{$view}' non trovata";
            return;
        }

        $app = $this;
        extract($data, EXTR_SKIP);
        include $file;
    }

    /**
     * Generate the complete CSS stylesheet for a new theme.
     *
     * Uses the color palette, font, and layout style from the theme manifest
     * to produce a fully functional CSS file with responsive breakpoints.
     *
     * @param array $manifest Theme manifest data (colors, font, layout, name)
     * @return string Complete CSS stylesheet content
     */
    private static function generateThemeCSS(array $manifest): string {
        $c = $manifest['colors'] ?? [];
        $primary = $c['primary'] ?? '#6366f1';
        $secondary = $c['secondary'] ?? '#a78bfa';
        $bg = $c['bg'] ?? '#0f172a';
        $text = $c['text'] ?? '#f1f5f9';
        $font = $manifest['font'] ?? 'Inter';

        // Calculate derived colors
        $bgLight = self::adjustBrightness($bg, 15);
        $textMuted = self::adjustBrightness($text, -30);
        $border = 'rgba(255,255,255,0.08)';

        return <<<CSS
/* ═══════════════════════════════════════════════════════════════
   {$manifest['name']} — Tema O-CMS
   Generato automaticamente dal Theme Wizard

   GUIDA ALLA PERSONALIZZAZIONE:

   1. COLORI: Modifica le variabili CSS in :root
      --primary     → Colore principale (pulsanti, link, accenti)
      --secondary   → Colore secondario (hover, gradienti)
      --bg          → Sfondo del sito
      --bg-light    → Sfondo card e sezioni alternate
      --text        → Colore testo principale
      --text-muted  → Colore testo secondario

   2. FONT: Cambia --font e il link Google Fonts nel template

   3. LAYOUT: Le classi principali sono:
      .container    → Larghezza massima del contenuto
      .site-header  → Barra di navigazione
      .hero         → Sezione hero della homepage
      .page-content → Contenuto pagine
      .articles-grid → Griglia articoli blog
      .site-footer  → Footer

   4. RESPONSIVE: I breakpoint sono in fondo al file
      Mobile: max-width 768px
      Tablet: max-width 1024px
   ═══════════════════════════════════════════════════════════════ */

:root {
    /* ── Colori Principali ── */
    --primary: {$primary};
    --primary-light: {$secondary};
    --secondary: {$secondary};

    /* ── Sfondi ── */
    --bg: {$bg};
    --bg-light: {$bgLight};

    /* ── Testo ── */
    --text: {$text};
    --text-muted: {$textMuted};

    /* ── Bordi e Utilità ── */
    --border: {$border};
    --radius: 12px;
    --font: '{$font}', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* ═══ RESET & BASE ═══ */
* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    line-height: 1.7;
    font-size: 16px;
    -webkit-font-smoothing: antialiased;
}
.container { width: 100%; max-width: 1100px; margin: 0 auto; padding: 0 24px; }
a { color: var(--primary-light); text-decoration: none; transition: color 0.2s; }
a:hover { color: var(--secondary); }
img { max-width: 100%; height: auto; }

/* ═══ HEADER / NAVIGAZIONE ═══
   La barra di navigazione è sticky (resta in alto durante lo scroll).
   Ha un effetto glassmorphism (sfocatura dello sfondo).
   Modifica backdrop-filter per cambiare l'effetto. */
.site-header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba({$bg}, 0.85);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 16px 0;
}
.site-header .container {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.site-logo {
    font-size: 1.3rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-light), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.site-nav { display: flex; gap: 32px; }
.nav-link {
    color: var(--text-muted);
    font-weight: 500;
    font-size: 0.9rem;
    transition: color 0.2s;
    position: relative;
}
.nav-link:hover { color: var(--text); }
.nav-link::after {
    content: '';
    position: absolute;
    bottom: -4px; left: 0;
    width: 0; height: 2px;
    background: var(--primary);
    transition: width 0.2s;
}
.nav-link:hover::after { width: 100%; }

/* ═══ HERO (Homepage) ═══
   La sezione principale della homepage.
   Modifica padding per cambiare l'altezza. */
.hero {
    padding: 100px 0 80px;
    text-align: center;
}
.hero h1 {
    font-size: 3rem;
    font-weight: 800;
    letter-spacing: -1px;
    margin-bottom: 20px;
    background: linear-gradient(135deg, var(--text), var(--text-muted));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero .content {
    font-size: 1.15rem;
    color: var(--text-muted);
    max-width: 600px;
    margin: 0 auto;
}

/* ═══ CONTENUTO PAGINA ═══ */
.page-content { padding: 80px 0; }
.page-content h1 {
    font-size: 2.5rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-bottom: 32px;
}
.page-content .content { max-width: 720px; }
.page-content .content h2 { font-size: 1.6rem; margin: 40px 0 16px; font-weight: 700; }
.page-content .content h3 { font-size: 1.3rem; margin: 32px 0 12px; font-weight: 600; }
.page-content .content p { margin-bottom: 16px; color: var(--text-muted); }
.page-content .content ul,
.page-content .content ol { margin-bottom: 16px; padding-left: 24px; color: var(--text-muted); }
.page-content .content li { margin-bottom: 8px; }
.page-content .content blockquote {
    border-left: 3px solid var(--primary);
    padding: 16px 24px;
    margin: 24px 0;
    background: var(--bg-light);
    border-radius: 0 var(--radius) var(--radius) 0;
    color: var(--text-muted);
    font-style: italic;
}
.page-content .content pre {
    background: var(--bg-light);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    overflow-x: auto;
    margin: 24px 0;
    font-size: 0.9rem;
}
.page-content .content code {
    background: var(--bg-light);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.875em;
}

/* ═══ BLOG — GRIGLIA ARTICOLI ═══
   Usa CSS Grid con auto-fill per adattarsi automaticamente.
   Cambia minmax(320px, ...) per card più larghe o strette. */
.articles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
}
.article-card {
    background: var(--bg-light);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.article-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.25);
}
.article-cover { height: 200px; background-size: cover; background-position: center; }
.article-body { padding: 24px; }
.article-cat {
    display: inline-block;
    font-size: 0.75rem; font-weight: 600;
    color: var(--primary-light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.article-body h2 { font-size: 1.2rem; font-weight: 700; margin-bottom: 8px; line-height: 1.3; }
.article-body h2 a { color: var(--text); }
.article-body h2 a:hover { color: var(--primary-light); }
.article-body p { font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 12px; }
.article-meta { font-size: 0.8rem; color: var(--text-muted); }

/* ═══ PULSANTI ═══ */
.btn-primary {
    display: inline-block;
    padding: 12px 28px;
    background: linear-gradient(135deg, var(--primary), var(--primary));
    color: white;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(99,102,241,0.3);
    color: white;
}

/* ═══ FOOTER ═══ */
.site-footer {
    border-top: 1px solid var(--border);
    padding: 32px 0;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* ═══ RESPONSIVE ═══
   Adatta il layout per schermi piccoli.
   Puoi aggiungere breakpoint personalizzati qui. */
@media (max-width: 768px) {
    .hero h1 { font-size: 2rem; }
    .hero { padding: 60px 0 40px; }
    .site-nav { gap: 16px; }
    .nav-link { font-size: 0.8rem; }
    .page-content h1 { font-size: 1.8rem; }
    .articles-grid { grid-template-columns: 1fr; }
}
CSS;
    }

    /**
     * Generate a PHP template file for a new theme.
     *
     * Produces complete PHP template files (home, page, blog, article, 404)
     * with header navigation, footer, and Google Fonts integration.
     *
     * @param string $type     Template type: 'home', 'page', 'blog', 'article', or '404'
     * @param array  $manifest Theme manifest data (name, font, colors)
     * @return string Complete PHP template file content
     */
    private static function generateThemeTemplate(string $type, array $manifest): string {
        $name = $manifest['name'] ?? 'Tema';
        $font = $manifest['font'] ?? 'Inter';

        // Common header and footer partials
        $head = '<!DOCTYPE html>
<html lang="<?= ocms_escape($app->config[\'language\'] ?? \'it\') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    ';

        $headerNav = '
    <!-- ═══ HEADER ═══
         Il menu viene caricato automaticamente da data/menus/main.json.
         La barra di ricerca punta a /search?q=... -->
    <header class="site-header">
        <div class="container">
            <a href="<?= ocms_base_url() ?>/" class="site-logo">
                <?= ocms_escape($app->config[\'site_name\'] ?? \'O-CMS\') ?>
            </a>
            <div style="display:flex;align-items:center;gap:24px;">
                <nav class="site-nav">
                    <?php $menu = $app->storage->find(\'menus\', \'main\');
                    if ($menu): foreach ($menu[\'items\'] as $item): ?>
                        <a href="<?= preg_match(\'#^https?://#\', $item[\'url\']) ? ocms_escape($item[\'url\']) : ocms_base_url() . ocms_escape($item[\'url\']) ?>" class="nav-link"<?= ($item[\'target\'] ?? \'_self\') === \'_blank\' ? \' target="_blank" rel="noopener"\' : \'\' ?>>
                            <?= ocms_escape($item[\'label\']) ?>
                        </a>
                    <?php endforeach; endif; ?>
                </nav>
                <form action="<?= ocms_base_url() ?>/search" method="GET" style="display:flex;align-items:center;background:var(--bg-light,#1e293b);border:1px solid var(--border,rgba(255,255,255,0.08));border-radius:8px;overflow:hidden;">
                    <input type="text" name="q" placeholder="Cerca..." value="<?= ocms_escape($_GET[\'q\'] ?? \'\') ?>" style="background:none;border:none;color:var(--text,#f1f5f9);padding:8px 12px;font-size:0.85rem;font-family:inherit;outline:none;width:140px;">
                    <button type="submit" style="background:none;border:none;color:var(--text-muted,#94a3b8);padding:8px 10px;cursor:pointer;display:flex;" aria-label="Cerca">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </header>';

        $footer = '
    <!-- ═══ FOOTER ═══
         Personalizza il footer qui. Puoi aggiungere colonne, social, ecc. -->
    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date(\'Y\') ?> <?= ocms_escape($app->config[\'site_name\'] ?? \'O-CMS\') ?></p>
        </div>
    </footer>
</body>
</html>';

        $fontLink = '<link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=' . urlencode($font) . ':wght@400;500;600;700;800&display=swap" rel="stylesheet">';
        $cssLink = '<link rel="stylesheet" href="<?= ocms_base_url() ?>/themes/<?= ocms_escape($app->config[\'theme\'] ?? \'flavor\') ?>/assets/css/style.css">';

        switch ($type) {
            case 'home':
                $title = '<title><?= ocms_escape($app->config[\'site_name\'] ?? \'O-CMS\') ?></title>
    <meta name="description" content="<?= ocms_escape($app->config[\'site_description\'] ?? \'\') ?>">';
                $content = '
    <!-- ═══ HOMEPAGE ═══
         Questa è la pagina principale del sito.
         La variabile $page contiene i dati della pagina "home" se esiste.
         Puoi aggiungere sezioni, slider, CTA, ecc. -->
    <main class="site-main">
        <section class="hero">
            <div class="container">
                <h1><?= isset($page) ? ocms_escape($page[\'title\']) : \'Benvenuto\' ?></h1>
                <div class="content">
                    <?= $page[\'content\'] ?? \'<p>Il tuo sito è pronto.</p>\' ?>
                </div>
            </div>
        </section>
    </main>';
                break;

            case 'page':
                $title = '<title><?= ocms_escape($page[\'meta\'][\'title\'] ?? $page[\'title\']) ?> — <?= ocms_escape($app->config[\'site_name\'] ?? \'O-CMS\') ?></title>
    <meta name="description" content="<?= ocms_escape($page[\'meta\'][\'description\'] ?? \'\') ?>">';
                $content = '
    <!-- ═══ PAGINA SINGOLA ═══
         $page contiene: title, slug, content (HTML), meta, template, status, author, created_at -->
    <main class="site-main">
        <article class="page-content">
            <div class="container">
                <h1><?= ocms_escape($page[\'title\']) ?></h1>
                <div class="content">
                    <?= $page[\'content\'] ?>
                </div>
            </div>
        </article>
    </main>';
                break;

            case 'blog':
                $title = '<title>Blog — <?= ocms_escape($app->config[\'site_name\'] ?? \'O-CMS\') ?></title>';
                $content = '
    <!-- ═══ LISTA ARTICOLI ═══
         $articles è un array di articoli pubblicati.
         Ogni articolo ha: title, slug, excerpt, content, cover_image, category, tags, author, created_at -->
    <main class="site-main">
        <section class="hero" style="padding:60px 0 40px;">
            <div class="container"><h1>Blog</h1></div>
        </section>
        <section style="padding:0 0 80px;">
            <div class="container">
                <?php if (empty($articles)): ?>
                    <p style="color:var(--text-muted);text-align:center;">Nessun articolo pubblicato.</p>
                <?php else: ?>
                    <div class="articles-grid">
                        <?php foreach ($articles as $a): ?>
                        <article class="article-card">
                            <?php if (!empty($a[\'cover_image\'])): ?>
                                <div class="article-cover" style="background-image:url(\'<?= ocms_base_url() . ocms_escape($a[\'cover_image\']) ?>\');"></div>
                            <?php endif; ?>
                            <div class="article-body">
                                <?php if (!empty($a[\'category\'])): ?>
                                    <span class="article-cat"><?= ocms_escape($a[\'category\']) ?></span>
                                <?php endif; ?>
                                <h2><a href="<?= ocms_base_url() ?>/blog/<?= ocms_escape($a[\'slug\']) ?>"><?= ocms_escape($a[\'title\']) ?></a></h2>
                                <?php if (!empty($a[\'excerpt\'])): ?>
                                    <p><?= ocms_escape($a[\'excerpt\']) ?></p>
                                <?php endif; ?>
                                <div class="article-meta">
                                    <span><?= ocms_format_date($a[\'created_at\'], \'d M Y\') ?></span>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>';
                break;

            case 'article':
                $title = '<title><?= ocms_escape($article[\'meta\'][\'title\'] ?? $article[\'title\']) ?> — <?= ocms_escape($app->config[\'site_name\'] ?? \'O-CMS\') ?></title>
    <meta name="description" content="<?= ocms_escape($article[\'meta\'][\'description\'] ?? $article[\'excerpt\'] ?? \'\') ?>">';
                $content = '
    <!-- ═══ ARTICOLO SINGOLO ═══
         $article contiene: title, slug, excerpt, content, cover_image, category, tags[], author, created_at -->
    <main class="site-main">
        <article class="page-content">
            <div class="container">
                <?php if (!empty($article[\'cover_image\'])): ?>
                    <img src="<?= ocms_base_url() . ocms_escape($article[\'cover_image\']) ?>" alt="<?= ocms_escape($article[\'title\']) ?>"
                         style="width:100%;max-height:400px;object-fit:cover;border-radius:12px;margin-bottom:32px;">
                <?php endif; ?>
                <h1><?= ocms_escape($article[\'title\']) ?></h1>
                <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:40px;">
                    <?= ocms_format_date($article[\'created_at\'], \'d M Y\') ?>
                    <?php if (!empty($article[\'author\'])): ?> — <?= ocms_escape($article[\'author\']) ?><?php endif; ?>
                </div>
                <div class="content"><?= $article[\'content\'] ?></div>
                <?php if (!empty($article[\'tags\'])): ?>
                <div style="margin-top:40px;padding-top:24px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap;">
                    <?php foreach ($article[\'tags\'] as $tag): ?>
                        <span style="padding:4px 12px;background:rgba(99,102,241,0.1);border-radius:6px;font-size:0.8rem;color:var(--primary-light);"><?= ocms_escape($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div style="margin-top:40px;"><a href="<?= ocms_base_url() ?>/blog">&larr; Torna al blog</a></div>
            </div>
        </article>
    </main>';
                break;

            case '404':
                $title = '<title>404 — Pagina non trovata</title>';
                $content = '
    <main class="site-main" style="display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;">
        <div>
            <h1 style="font-size:6rem;font-weight:800;opacity:0.2;margin-bottom:0;">404</h1>
            <p style="font-size:1.3rem;color:var(--text-muted);margin-bottom:24px;">Pagina non trovata</p>
            <a href="<?= ocms_base_url() ?>/" class="btn-primary">Torna alla Home</a>
        </div>
    </main>';
                $headerNav = ''; // 404 page without header
                $footer = '</body></html>';
                break;

            default:
                return "<?php // Template: {$type} ?>\n";
        }

        return $head . $title . "\n    " . $fontLink . "\n    " . $cssLink . "\n</head>\n<body>" . $headerNav . $content . $footer;
    }

    /**
     * Generate the README documentation file for a new theme.
     *
     * @param array $manifest Theme manifest data (name, id, etc.)
     * @return string Markdown-formatted README content
     */
    private static function generateThemeReadme(array $manifest): string {
        $name = $manifest['name'] ?? 'Tema';
        $id = $manifest['id'] ?? 'tema';
        return <<<MD
# {$name} — Tema O-CMS

## Struttura

```
{$id}/
├── theme.json           ← Manifest del tema (nome, colori, font, autore)
├── README.md            ← Questo file
├── assets/
│   ├── css/style.css    ← CSS principale — MODIFICA QUESTO per cambiare l'aspetto
│   ├── js/app.js        ← JavaScript personalizzato
│   └── img/             ← Immagini del tema (logo, sfondi, ecc.)
├── layouts/             ← Layout personalizzati (opzionale)
└── templates/           ← Template delle pagine
    ├── home.php         ← Homepage
    ├── page.php         ← Pagina generica
    ├── blog.php         ← Lista articoli
    ├── article.php      ← Articolo singolo
    └── 404.php          ← Pagina errore 404
```

## Come personalizzare

### Colori
Modifica le variabili CSS in `assets/css/style.css` nella sezione `:root`:
```css
:root {
    --primary: #6366f1;      /* Colore principale */
    --bg: #0f172a;           /* Sfondo */
    --text: #f1f5f9;         /* Testo */
}
```

### Font
1. Cambia `--font` in style.css
2. Aggiorna il link Google Fonts in ogni template

### Template
I template hanno accesso a queste variabili:
- `\$app` → istanza dell'applicazione (config, storage, auth)
- `\$page` → dati della pagina corrente (in page.php e home.php)
- `\$article` → dati dell'articolo (in article.php)
- `\$articles` → lista articoli (in blog.php)

### Funzioni utili nei template
- `ocms_base_url()` → URL base del sito
- `ocms_escape(\$str)` → Sanitizza per HTML
- `ocms_format_date(\$date, \$format)` → Formatta data
- `\$app->storage->find('menus', 'main')` → Carica un menu
- `\$app->config['site_name']` → Nome del sito
MD;
    }

    /**
     * Adjust the brightness of a hex color by a given percentage.
     *
     * @param string $hex     Hex color string (e.g., '#0f172a' or '0f172a')
     * @param int    $percent Brightness adjustment (-100 to 100; positive = lighter, negative = darker)
     * @return string Adjusted hex color string with '#' prefix
     */
    private static function adjustBrightness(string $hex, int $percent): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + (int)(255 * $percent / 100)));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + (int)(255 * $percent / 100)));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + (int)(255 * $percent / 100)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Authenticate an API request via session or Bearer token.
     *
     * Checks for an active session first, then falls back to the
     * Authorization: Bearer header. Sends a 401 JSON response and
     * exits if authentication fails.
     *
     * @param App $app Application instance
     * @return array Authenticated user data
     */
    private function apiAuth(App $app): array {
        // If there is an active session, use the session user
        if ($app->auth->check()) {
            return $app->auth->user();
        }

        // Check for Bearer token in the Authorization header
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $header, $m)) {
            $user = $app->auth->authenticateByToken($m[1]);
            if ($user) return $user;
        }

        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Non autenticato. Usa header: Authorization: Bearer <token>']);
        exit;
    }

    /**
     * Enforce a minimum role requirement for an API endpoint.
     *
     * Sends a 403 JSON response and exits if the user's role
     * is below the required minimum.
     *
     * @param array  $user    Authenticated user data
     * @param string $minRole Minimum required role (e.g., 'editor', 'administrator')
     * @return void
     */
    private function apiRequireRole(array $user, string $minRole): void {
        if (!$this->apiHasRole($user, $minRole)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Permesso negato. Ruolo minimo richiesto: ' . $minRole]);
            exit;
        }
    }

    /**
     * Check whether an API user has at least the specified role.
     *
     * @param array  $user User data array (must include 'role')
     * @param string $role Role to check against (e.g., 'editor', 'administrator')
     * @return bool True if the user's role meets or exceeds the required role
     */
    private function apiHasRole(array $user, string $role): bool {
        $hierarchy = $this->auth->getRoleHierarchy();
        $userLevel = $hierarchy[$user['role']] ?? 0;
        $requiredLevel = $hierarchy[$role] ?? 0;
        return $userLevel >= $requiredLevel;
    }

    /**
     * Inject the "Powered by O-CMS" badge before </body>.
     *
     * This badge is part of the O-CMS free license. It must remain visible
     * on all pages rendered by the CMS. The badge is intentionally injected
     * by the core engine so it cannot be removed by editing theme templates.
     *
     * @param string $html Full HTML output
     * @return string HTML with the powered-by badge injected
     */
    private function injectPoweredBy(string $html): string {
        $badge = '<div style="text-align:center;padding:12px 0 10px;font-size:0.7rem;'
               . 'opacity:0.55;font-family:system-ui,sans-serif;" id="ocms-pb">'
               . 'Powered by <a href="https://ivanbertotto.it/s/o-cms" target="_blank" '
               . 'rel="noopener" style="color:inherit;text-decoration:underline;">O-CMS</a>'
               . '</div>';

        // Inject before </body>
        $pos = strripos($html, '</body>');
        if ($pos !== false) {
            return substr_replace($html, $badge . "\n", $pos, 0);
        }
        // Fallback: append at the end
        return $html . $badge;
    }

    /**
     * Send a one-time anonymous installation notification to the O-CMS project.
     *
     * This feature is fully transparent and opt-in. During installation, users
     * are informed about what data is sent (domain, PHP version, CMS version)
     * and can disable it. Only fires once, on the first admin panel access.
     */
    private function sendInstallNotification(): void {
        $endpoint = 'https://ivanbertotto.it/ocms-ping.php';
        $data = [
            'domain'      => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'php_version' => PHP_VERSION,
            'cms_version' => defined('OCMS_VERSION') ? OCMS_VERSION : '1.0.0',
            'timestamp'   => date('c'),
        ];

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode($data),
                'timeout' => 3,
            ],
        ]);

        @file_get_contents($endpoint, false, $context);

        // Mark as notified regardless of success (only try once)
        $this->config['installation_notified'] = true;
        $this->storage->writeFile('config.json', $this->config);
    }
}
