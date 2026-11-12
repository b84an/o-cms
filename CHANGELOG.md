# Changelog

All notable changes to O-CMS will be documented in this file.

## [2.0.2] - 2026-11-12

### Correzioni
- Revisioni: pulsante "Confronta" verso la vista del confronto; l'anteprima di una revisione gira in un iframe isolato (gli script contenuti non vengono eseguiti nell'admin).
- Tema flavor: i pulsanti `.btn` scritti dentro articoli e pagine non prendono più lo stile dei link; le pagine statiche hanno righe di lunghezza leggibile, allineate al titolo.

## [2.0.1] - 2026-11-12

### Sicurezza
- FoglioFirme: tentativi con il codice d'accesso sbagliato limitati per sessione e per IP.
- Quiz con codice d'accesso: lo salta solo lo staff (editor o superiore), non più qualsiasi utente registrato.

## [2.0.0] - 2026-11-12

Base: il codice in produzione su ivanbertotto.it (il più aggiornato), più il guscio di distribuzione del repo.

### Sicurezza
- Form contatti: chiusa l'iniezione di comandi SMTP (a capo negli header, dot-stuffing del corpo), destinatario da `admin_email`, controllo di origine, limite per IP.
- Upload: estensione coerente con il contenuto reale, nessuno script eseguibile (`.php`, `.phtml`, `.phar`, `.htaccess`…, anche come doppia estensione); `.htaccess` blocca PHP in `uploads/` e l'accesso diretto a `core/`, `admin/views/`, `themes/*/templates/`.
- Ruoli: nessuno può assegnare un ruolo superiore al proprio né modificare utenti di livello superiore; ruolo e stato ricaricati a ogni richiesta (un utente disattivato perde subito l'accesso).
- CSRF: un token di sessione vuoto non è più valido (prima `hash_equals('', '')` faceva passare le richieste senza token); CSRF anche sugli upload di lezioni e gallerie e sulle API usate con la sessione (`X-CSRF-Token`).
- Sanitizer HTML: attributi `on*` e URL `javascript:`/`vbscript:`/`data:` bloccati anche se offuscati; non rovina più gli esempi di codice nel testo.
- Quiz: il codice d'accesso protegge anche l'invio; limite ai tentativi; risposte validate; le risposte corrette non arrivano più al browser (tema flavor).
- Login: limiti per sessione, IP e coppia username+IP, rallentamento progressivo per account.
- `/api/config` non espone più chiavi IA e SMTP; FoglioFirme pubblico non mostra più nomi né codice d'accesso.
- Errori PHP non più visibili in pagina (si riattivano con il file `data/.debug`); CSV protetti dall'iniezione di formule.

### Correzioni
- Scritture JSON con lock e file temporaneo univoco: niente più firme, compilazioni o statistiche perse con accessi simultanei, né salvataggi dell'admin sovrascritti dal contatore visite.
- Salvare un form non cancella più le compilazioni raccolte.
- Rinominare articoli, pagine e lezioni conserva visite, autore, data, revisioni e campi aggiuntivi; uno slug già usato non sovrascrive più un altro contenuto.
- Salvare le Impostazioni non cancella più le chiavi che il form non mostra e non lascia vuota la password SMTP; sitemap con URL assoluti (pagine, articoli, lezioni, gallerie, quiz pubblici) e robots.txt con la riga `Sitemap:`.
- Pubblicazione programmata (`publish_at`) confrontata come istante reale, rispettata anche da ricerca e sitemap.
- Editor e publisher dopo il login atterrano su una pagina consentita invece che su un 403.
- La modalità manutenzione ora funziona davvero (pagina 503 per il pubblico, lo staff naviga).
- Captcha dei form sempre verificato; un solo captcha per pagina articolo (le risposte ai commenti funzionano).
- Le API REST rispondono anche su `/api/...` come da documentazione.
- Cartelle `data/` mancanti create al volo (prima: warning su ogni pagina).

### Novità
- Design system condiviso "notte sul lago" (`assets/ocms/`): glassmorfismo blu, aurora animata, tema chiaro/scuro automatico, font ospitati in locale.
- Confronto riga per riga tra una revisione e la versione attuale.
- Revisioni anche per le pagine e per le modifiche fatte via API.
- Updater: verifica SHA-256 del pacchetto, protezione zip slip, rimozione dei file obsoleti; `tools/build-release.sh` prepara pacchetto e checksum.
- Banco di prova: `tests/` (unit e HTTP) e `tests/serve.sh` per provare il CMS in locale su una copia dei dati.

## [1.0.0] - 2026-04-13

### Initial Release

- **Core Engine** — Singleton-based application with modular architecture
- **JSON Storage** — Flat-file persistence with atomic writes and concurrent access handling
- **Router** — Regex-based URL routing with parameter extraction
- **Authentication** — 5-tier role hierarchy (Super Admin, Admin, Editor, Publisher, Registered)
- **Session Management** — Secure sessions with HttpOnly cookies and SameSite policy
- **Admin Panel** — Full-featured dark-themed administration interface
- **Pages** — Static page management with WYSIWYG editor and SEO fields
- **Articles** — Blog system with categories, tags, cover images, and excerpts
- **Comments** — Threaded comment system with moderation and admin replies
- **Menu Builder** — Drag-and-drop navigation builder with nested items
- **Media Manager** — File upload with gallery view and metadata
- **Form Builder** — Visual form creator with 10 field types and email notifications
- **Layout Builder** — Gantry-style visual layout editor with 18 module types
- **Theme Engine** — Pluggable theme system with wizard and ZIP install/export
- **Extension System** — Full plugin architecture with hooks, boot lifecycle, and admin menus
- **Search Engine** — Full-text search with fuzzy matching and weighted relevance scoring
- **Analytics** — Built-in anonymous visitor tracking with daily aggregation
- **Backup System** — Data and full backup with migration installer
- **REST API** — JSON endpoints for articles, pages, categories, comments, media, menus, users, settings
- **AI Integration** — Multi-provider AI content generation (Anthropic, OpenAI, Groq, Mistral, Google)
- **Lessons** — Educational content type with file management
- **Quizzes** — Interactive quiz system with multiple question types
- **Galleries** — Image gallery management with grid/masonry layouts
- **SEO** — Per-page meta tags, sitemap generation, robots.txt, code injection
- **Installation Wizard** — 7-step guided setup with requirements check
- **Security** — CSRF protection, bcrypt passwords, rate limiting, input sanitization
