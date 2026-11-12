# Changelog

All notable changes to O-CMS will be documented in this file.

## [2.0.6] - 2026-11-12

### Novità
- Tema flavor: le pagine esterne al CMS che usano header e footer del tema (mini-app nelle sottocartelle del sito) possono aggiungere i propri fogli di stile e script con `$flavor['styles']` e `$flavor['scripts']`.

## [2.0.5] - 2026-11-12

Nuova verifica completa di sicurezza e bug (core, admin, tema, installer).

### Sicurezza
- Gallerie e lezioni: uno slug d'origine o un parametro con `../` o `..` non può più spostare o cancellare cartelle fuori posto (prima un editor poteva rendere pubblica `data/` o cancellare `uploads/`). Il router rifiuta i parametri `.` e `..`.
- Installer dei backup (`installer.php`): non si può più rieseguire dopo il ripristino (prima permetteva a chiunque di creare un super amministratore), CSRF, username validato, lo ZIP del backup viene cancellato.
- `install.php`: bloccato anche sui siti 1.x senza `data/.installed`; con la sessione scaduta non crea più l'utente admin/changeme; campi validati.
- XSS chiuse: galleria degli articoli via API, shortcode `[gallery:...]` (anche la lightbox ora funziona), suggerimenti della ricerca admin, finestra di eliminazione categoria, risposta del test IA, tag delle sezioni di layout.
- API: campi validati per tipo (un solo articolo con `tags` scritto male mandava in errore tutto il blog).
- Sessione: cookie proprio per ogni installazione e limitato al suo percorso (una sandbox sullo stesso dominio non condivide più il login). Dopo l'aggiornamento serve rientrare una volta.
- Limiti di lunghezza su firme, commenti e form; limiti per IP su commenti, form e registrazione; statistiche con un tetto ai referrer; `check-email` dei quiz protetto come l'invio; logout solo con POST; chiave IA solo su TLS verificato; redirect solo verso pagine del sito.

### Correzioni
- Salvare dall'admin un articolo o una pagina non distrugge più iframe, script, form e tag semantici: gli amministratori salvano HTML libero (come già con le API), l'editor lavora in un iframe isolato. La pagina contatto si può di nuovo salvare.
- Salvare un form non gli cambia più lo slug (gli shortcode `[form:...]` restano validi); le opzioni di select/radio/checkbox si salvano una per riga.
- Rinominare quiz, gallerie e menu non sovrascrive più un altro elemento; i risultati seguono il quiz rinominato.
- Quiz: le impostazioni dei quiz vecchi non vengono azzerate al salvataggio; l'intestazione del CSV non diventa una domanda; virgolette nei testi importati conservate; sui PC condivisi il quiz salvato dice di chi è.
- FoglioFirme: codice senza distinzione tra maiuscole e minuscole; dopo un errore il modulo resta compilato; la proiezione mostra "chiusa" dopo la chiusura automatica.

## [2.0.4] - 2026-11-12

### Novità
- Elenco articoli nell'admin: colonna **Visite** e ordinamento cliccando sulle intestazioni (titolo, categoria, stato, visite, data); l'ordine scelto viene ricordato.

### Correzioni
- Elenco articoli: il pulsante "Elimina" di ogni riga stava in un form annidato dentro quello delle azioni di massa e il browser lo ignorava (cliccandolo partiva lo spostamento di categoria). Ora elimina davvero.

## [2.0.3] - 2026-11-12

### Correzioni
- Il template di pagina "Larghezza Piena", già proposto nell'admin, ora funziona nel tema flavor: niente titolo automatico e contenuto a tutta larghezza, per le pagine che hanno una propria impaginazione.

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
