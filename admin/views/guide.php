<?php
$pageTitle = 'Guida';
$activeMenu = 'guide';
ob_start();
?>
<style>
.guide-container { max-width:860px; }
.guide-section { margin-bottom:32px; }
.guide-section h2 {
    font-size:1.3rem; font-weight:800; margin-bottom:16px;
    padding-bottom:12px; border-bottom:1px solid var(--border);
}
.guide-section h3 { font-size:1rem; font-weight:700; margin:20px 0 8px; color:var(--primary-light); }
.guide-section p { color:var(--text-muted); font-size:0.9rem; line-height:1.7; margin-bottom:12px; }
.guide-section ul, .guide-section ol { color:var(--text-muted); font-size:0.9rem; line-height:1.8; padding-left:20px; margin-bottom:12px; }
.guide-section li { margin-bottom:4px; }
.guide-section code { background:var(--bg-input); padding:2px 6px; border-radius:4px; font-size:0.85em; }
.guide-section .tip {
    background:rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.2);
    border-radius:10px; padding:14px 18px; margin:12px 0; font-size:0.85rem; color:var(--text-muted);
}
.guide-section .tip strong { color:var(--primary-light); }
.guide-section .warn {
    background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.2);
    border-radius:10px; padding:14px 18px; margin:12px 0; font-size:0.85rem; color:var(--text-muted);
}
.guide-section .warn strong { color:var(--warning); }
.guide-toc { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:32px; }
.guide-toc h3 { margin:0 0 12px; font-size:1rem; }
.guide-toc a { display:block; padding:4px 0; color:var(--text-muted); font-size:0.85rem; }
.guide-toc a:hover { color:var(--primary-light); }
.shortcut { display:inline-flex; gap:4px; }
.shortcut kbd {
    background:var(--bg-input); border:1px solid var(--border); border-radius:4px;
    padding:2px 8px; font-size:0.8rem; font-family:monospace;
}
</style>
<?php $headExtra = ob_get_clean(); ob_start(); ?>

<div class="page-header">
    <h1>Guida O-CMS</h1>
    <span style="color:var(--text-muted);font-size:0.85rem;">v1.0</span>
</div>

<div class="guide-container">

<!-- INDICE -->
<div class="guide-toc">
    <h3>Indice</h3>
    <a href="#start">1. Per Iniziare</a>
    <a href="#pages">2. Gestione Pagine</a>
    <a href="#articles">3. Blog e Articoli</a>
    <a href="#media">4. Media Manager</a>
    <a href="#menus">5. Menu di Navigazione</a>
    <a href="#forms">6. Form Builder</a>
    <a href="#layouts">7. Layout Builder</a>
    <a href="#themes">8. Temi</a>
    <a href="#users">9. Utenti e Ruoli</a>
    <a href="#search">10. Ricerca</a>
    <a href="#extensions">11. Estensioni</a>
    <a href="#backup">12. Backup e Migrazione</a>
    <a href="#settings">13. Impostazioni e SEO</a>
    <a href="#api">14. API REST</a>
    <a href="#security">15. Sicurezza</a>
</div>

<!-- 1. PER INIZIARE -->
<div class="guide-section" id="start">
    <h2>1. Per Iniziare</h2>
    <p>O-CMS è un CMS basato su file JSON — nessun database richiesto. Tutti i dati sono salvati come file JSON nella cartella <code>data/</code>.</p>

    <h3>Primo accesso</h3>
    <p>Le credenziali predefinite sono <code>admin</code> / <code>admin</code>. Cambia subito la password da <strong>Utenti → Modifica</strong>.</p>

    <h3>Struttura della Dashboard</h3>
    <ul>
        <li><strong>Sidebar sinistra</strong>: navigazione a tutte le sezioni</li>
        <li><strong>Barra ricerca</strong> (in alto nella sidebar): cerca ovunque, premi Invio per la ricerca avanzata</li>
        <li><strong>Statistiche</strong>: conteggi pagine, articoli, utenti, media</li>
        <li><strong>Azioni rapide</strong>: link diretti alle azioni più comuni</li>
    </ul>

    <div class="tip"><strong>Suggerimento:</strong> Puoi cercare qualsiasi cosa dalla barra di ricerca nella sidebar — pagine, articoli, utenti, media, form, menu.</div>
</div>

<!-- 2. PAGINE -->
<div class="guide-section" id="pages">
    <h2>2. Gestione Pagine</h2>
    <p>Le pagine sono i contenuti statici del sito (Home, Chi Siamo, Contatti, ecc.).</p>

    <h3>Creare una pagina</h3>
    <ol>
        <li>Vai a <strong>Pagine → + Nuova Pagina</strong></li>
        <li>Inserisci il <strong>titolo</strong> (lo slug URL viene generato automaticamente)</li>
        <li>Scrivi il contenuto nell'<strong>editor visuale</strong> (Jodit)</li>
        <li>Compila i campi <strong>SEO</strong> (meta title, description) — controlla l'anteprima Google in basso</li>
        <li>Scegli <strong>Salva Bozza</strong> o <strong>Pubblica</strong></li>
    </ol>

    <h3>Editor visuale</h3>
    <p>L'editor supporta: testo formattato, liste, immagini, tabelle, video, link, codice sorgente HTML. Le immagini vengono incorporate in base64 — per immagini esterne usa il pulsante immagine e inserisci l'URL.</p>

    <h3>Template</h3>
    <p>Puoi assegnare template diversi a ogni pagina: <strong>Pagina Standard</strong>, <strong>Homepage</strong>, <strong>Larghezza Piena</strong>.</p>
</div>

<!-- 3. ARTICOLI -->
<div class="guide-section" id="articles">
    <h2>3. Blog e Articoli</h2>
    <p>Gli articoli sono i contenuti del blog, visibili su <code>/blog</code>.</p>

    <h3>Creare un articolo</h3>
    <ol>
        <li>Vai a <strong>Articoli → + Nuovo Articolo</strong></li>
        <li>Compila: titolo, estratto, contenuto, immagine di copertina</li>
        <li>Assegna una <strong>categoria</strong> e <strong>tag</strong> (separati da virgola — vengono creati automaticamente)</li>
        <li>Pubblica</li>
    </ol>

    <h3>Categorie</h3>
    <p>Gestisci le categorie da <strong>Articoli → Categorie</strong>. Puoi crearle al volo digitando il nome e cliccando "Aggiungi".</p>
</div>

<!-- 4. MEDIA -->
<div class="guide-section" id="media">
    <h2>4. Media Manager</h2>
    <p>Carica e gestisci immagini, PDF, documenti e video.</p>

    <h3>Caricare file</h3>
    <ul>
        <li><strong>Trascina e rilascia</strong> i file nell'area di upload, oppure clicca per selezionare</li>
        <li>Upload multiplo supportato</li>
        <li>Limite: <strong>10MB</strong> per file</li>
        <li>Formati: JPEG, PNG, GIF, WebP, PDF, ZIP, MP4, MP3</li>
    </ul>

    <h3>Usare i media</h3>
    <p>Clicca su un file per vedere il <strong>dettaglio</strong> con URL copiabile. Usa l'URL nel campo "immagine di copertina" degli articoli o nell'editor.</p>
</div>

<!-- 5. MENU -->
<div class="guide-section" id="menus">
    <h2>5. Menu di Navigazione</h2>
    <p>I menu vengono mostrati nell'header del sito.</p>

    <h3>Modificare il menu</h3>
    <ol>
        <li>Vai a <strong>Menu → main</strong></li>
        <li><strong>Aggiungi pagine</strong> selezionandole dal pannello destro</li>
        <li><strong>Aggiungi link personalizzati</strong> con URL e etichetta</li>
        <li><strong>Riordina</strong> trascinando le voci</li>
        <li><strong>Sotto-voci</strong>: clicca "+ Sotto-voce" su una voce per creare un sotto-menu</li>
        <li>Clicca <strong>Salva Menu</strong></li>
    </ol>

    <div class="tip"><strong>Suggerimento:</strong> Puoi creare più menu (es. "footer") e usarli nel Layout Builder con il modulo Menu.</div>
</div>

<!-- 6. FORM -->
<div class="guide-section" id="forms">
    <h2>6. Form Builder</h2>
    <p>Crea moduli di contatto, sondaggi e qualsiasi tipo di form.</p>

    <h3>Creare un form</h3>
    <ol>
        <li>Vai a <strong>Form → + Nuovo Form</strong></li>
        <li>Dai un nome al form</li>
        <li>Clicca sui <strong>tipi di campo</strong> (Testo, Email, Textarea, Select, ecc.) per aggiungerli</li>
        <li>Per ogni campo: clicca &#9660; per espandere e configurare label, nome, placeholder, obbligatorietà</li>
        <li>Per Select e Radio: scrivi le opzioni (una per riga)</li>
        <li>Configura <strong>email notifica</strong> e <strong>messaggio di successo</strong> nel pannello destro</li>
        <li>Salva</li>
    </ol>

    <h3>Integrare il form in una pagina</h3>
    <p>Dopo il salvataggio, trovi l'<strong>embed code</strong> nella pagina di modifica del form. Copia il codice HTML nell'editor di una pagina (in modalità sorgente).</p>

    <h3>Vedere le risposte</h3>
    <p>Vai a <strong>Form → Risposte</strong> per ogni form. I dati sono salvati in JSON e scaricabili.</p>
</div>

<!-- 7. LAYOUT BUILDER -->
<div class="guide-section" id="layouts">
    <h2>7. Layout Builder</h2>
    <p>Il Layout Builder è un editor visuale per costruire la struttura delle pagine, simile a Gantry Framework.</p>

    <h3>Concetti base</h3>
    <ul>
        <li><strong>Layout Base</strong>: ereditato da tutte le pagine. Definisce header e footer globali.</li>
        <li><strong>Sezioni</strong>: blocchi verticali (header, main, footer, hero, cta...)</li>
        <li><strong>Righe</strong>: dentro ogni sezione</li>
        <li><strong>Colonne</strong>: dentro ogni riga — scegli il preset (1, 2, 3, 4, 8+4, 4+8, 3+6+3)</li>
        <li><strong>Moduli</strong>: i contenuti — trascinali dalla palette dentro le colonne</li>
    </ul>

    <h3>18 moduli disponibili</h3>
    <p>Titolo, Testo, Rich Text, Immagine, Galleria, Video, Pulsante, Card, Articoli, HTML, Spaziatore, Divisore, Logo, Menu, Contenuto Pagina, Icona, Social, Breadcrumb.</p>

    <h3>Come usarlo</h3>
    <ol>
        <li>Vai a <strong>Layout Builder</strong></li>
        <li>Modifica il <strong>Layout Base</strong> per header/footer globali</li>
        <li>Crea layout aggiuntivi per pagine specifiche</li>
        <li><strong>Trascina</strong> i moduli dalla palette (pannello destro) alle colonne</li>
        <li>Clicca su un modulo per configurarlo</li>
        <li>Usa <strong>Anteprima</strong> per vedere il risultato</li>
        <li><strong>Salva</strong></li>
    </ol>

    <div class="tip"><strong>Suggerimento:</strong> Clicca il pulsante &#9638; sulla riga per cambiare rapidamente il layout delle colonne.</div>

    <h3>Come applicare un layout a una pagina</h3>
    <ol>
        <li>Vai a <strong>Layout Builder</strong> e crea/modifica i tuoi layout</li>
        <li>Vai a <strong>Pagine → Modifica</strong> una pagina</li>
        <li>Nel pannello destro, sezione <strong>Layout</strong>, scegli dal dropdown:
            <ul>
                <li><strong>Nessuno</strong>: la pagina usa il template PHP classico</li>
                <li><strong>Layout Base (ereditato)</strong>: usa il layout base con header + contenuto + footer globali</li>
                <li><strong>Un layout personalizzato</strong>: usa quel layout specifico — le sezioni con lo stesso ID del base vengono sovrascritte, le altre ereditate</li>
            </ul>
        </li>
        <li>Salva la pagina</li>
    </ol>

    <h3>Come funziona l'ereditarietà</h3>
    <p>Ogni layout specifico <strong>eredita dal Layout Base</strong>. In pratica:</p>
    <ul>
        <li>Il Layout Base definisce le sezioni globali: <code>header</code>, <code>main</code>, <code>footer</code></li>
        <li>Un layout specifico (es. "Landing Page") può avere sezioni aggiuntive (es. <code>hero</code>, <code>cta</code>) o sovrascrivere quelle del base</li>
        <li>Se una sezione nel layout specifico ha lo <strong>stesso ID</strong> di una sezione nel base, la sovrascrive</li>
        <li>Se il layout specifico non ha una sezione del base, questa viene ereditata</li>
    </ul>

    <h3>Esempio pratico</h3>
    <p><strong>Layout Base</strong> con: header (logo + menu) → main (contenuto) → footer (copyright)</p>
    <p><strong>Layout "Landing"</strong> con: hero (titolo grande + CTA) → features (3 colonne card) → main (contenuto)</p>
    <p>Risultato: la Landing avrà header e footer dal base + hero e features propri + contenuto della pagina.</p>

    <h3>Il modulo "Contenuto Pagina"</h3>
    <p>Questo modulo speciale inserisce automaticamente il <strong>contenuto HTML scritto nell'editor</strong> della pagina. Posizionalo dove vuoi che appaia il testo della pagina all'interno del layout.</p>

    <div class="warn"><strong>Nota:</strong> Se una pagina ha "Layout: Nessuno", usa il template PHP tradizionale. Le due modalità sono intercambiabili — puoi passare dall'una all'altra in qualsiasi momento.</div>
</div>

<!-- 8. TEMI -->
<div class="guide-section" id="themes">
    <h2>8. Temi</h2>
    <p>I temi controllano l'aspetto del sito frontend.</p>

    <h3>Creare un tema</h3>
    <ol>
        <li>Vai a <strong>Temi → + Crea Tema</strong></li>
        <li><strong>Step 1</strong>: nome, descrizione, autore</li>
        <li><strong>Step 2</strong>: scegli i colori (4 color picker + 8 preset pronti)</li>
        <li><strong>Step 3</strong>: scegli il font (8 opzioni) e lo stile del layout</li>
        <li><strong>Step 4</strong>: anteprima e conferma</li>
    </ol>

    <p>Il wizard genera: CSS completo con commenti guida, 5 template PHP, file JS, README con istruzioni dettagliate.</p>

    <h3>Installare un tema</h3>
    <p>Clicca <strong>Installa da ZIP</strong> e carica un pacchetto .zip contenente la cartella del tema con <code>theme.json</code>.</p>

    <h3>Personalizzare</h3>
    <p>Modifica i file CSS in <code>themes/{nome}/assets/css/style.css</code>. Le variabili colore sono in <code>:root</code>. I template PHP sono in <code>themes/{nome}/templates/</code>.</p>
</div>

<!-- 9. UTENTI -->
<div class="guide-section" id="users">
    <h2>9. Utenti e Ruoli</h2>

    <h3>5 livelli di ruolo</h3>
    <ul>
        <li><strong>Super Amministratore</strong>: accesso completo (impostazioni, backup, estensioni, temi, utenti, SMTP)</li>
        <li><strong>Amministratore</strong>: tutto tranne backup/restore e gestione estensioni</li>
        <li><strong>Editor</strong>: gestione contenuti (pagine, articoli, media, menu, form) + valida i Publisher</li>
        <li><strong>Publisher</strong>: può creare contenuti, deve essere validato da un Editor</li>
        <li><strong>Registrato</strong>: accesso base dopo attivazione via email</li>
    </ul>

    <h3>Registrazione utenti</h3>
    <p>Se abilitata nelle Impostazioni, gli utenti possono registrarsi dal frontend. Riceveranno un'email con un link di attivazione. Dopo l'attivazione entrano come <em>Registrato</em>. Un Editor o superiore può poi promuoverli a Publisher.</p>

    <div class="warn"><strong>Importante:</strong> Per la registrazione, configura l'SMTP nelle Impostazioni. Cambia la password predefinita di admin subito dopo l'installazione!</div>
</div>

<!-- 10. RICERCA -->
<div class="guide-section" id="search">
    <h2>10. Ricerca</h2>

    <h3>Ricerca Admin (sidebar)</h3>
    <p>Digita nella barra in alto nella sidebar per cercare istantaneamente in tutto il CMS. Premi <strong>Invio</strong> per la ricerca avanzata.</p>

    <h3>Ricerca Avanzata</h3>
    <p>Accessibile da <strong>sidebar → Invio</strong> o <strong>link "Ricerca avanzata"</strong> nei risultati rapidi.</p>
    <ul>
        <li><strong>Filtri per tipo</strong>: pagine, articoli, categorie, utenti, media, form, menu</li>
        <li><strong>Filtri per stato</strong>: pubblicato / bozza</li>
        <li><strong>Filtri per categoria</strong> e <strong>range date</strong></li>
        <li><strong>Suggerimenti</strong> automatici sotto la barra</li>
        <li>Risultati ordinati per <strong>rilevanza</strong> con evidenziazione match</li>
    </ul>

    <h3>Ricerca Frontend</h3>
    <p>La barra "Cerca..." nell'header del sito permette ai visitatori di cercare tra pagine e articoli pubblicati.</p>
</div>

<!-- 11. ESTENSIONI -->
<div class="guide-section" id="extensions">
    <h2>11. Estensioni</h2>
    <p>Le estensioni aggiungono funzionalità al CMS.</p>

    <h3>Creare un'estensione</h3>
    <ol>
        <li>Vai a <strong>Estensioni → + Crea Estensione</strong></li>
        <li>Il wizard genera: <code>boot.php</code> (entry point), viste, assets, install/uninstall scripts</li>
        <li>Modifica i file in <code>extensions/{nome}/</code></li>
        <li>Attiva dal toggle nella lista estensioni</li>
    </ol>

    <h3>boot.php</h3>
    <p>Il file <code>boot.php</code> viene eseguito ad ogni richiesta quando l'estensione è attiva. Ha accesso a:</p>
    <ul>
        <li><code>$app</code>: l'istanza dell'applicazione (router, storage, auth, config)</li>
        <li><code>$extension</code>: il manifest dell'estensione</li>
        <li>Può registrare rotte: <code>$app->router->get('/ext/my-ext', function() {...})</code></li>
        <li>Può usare gli hook: <code>Hooks::on('event', callback)</code></li>
    </ul>

    <h3>Installare / Distribuire</h3>
    <p><strong>Scarica ZIP</strong> per distribuire. <strong>Installa da ZIP</strong> per installare estensioni di terze parti.</p>
</div>

<!-- 12. BACKUP -->
<div class="guide-section" id="backup">
    <h2>12. Backup e Migrazione</h2>

    <h3>Due tipi di backup</h3>
    <ul>
        <li><strong>Backup Dati</strong>: salva solo JSON + file caricati. Per ripristino sulla stessa installazione.</li>
        <li><strong>Backup Completo + Installer</strong>: salva TUTTO il CMS con un file <code>installer.php</code> per migrare su un altro server.</li>
    </ul>

    <h3>Migrare il sito</h3>
    <ol>
        <li>Crea un <strong>Backup Completo</strong></li>
        <li>Scarica il file ZIP</li>
        <li>Sul nuovo server: carica il ZIP e <code>installer.php</code></li>
        <li>Apri <code>installer.php</code> nel browser</li>
        <li>Segui i 3 step: verifica requisiti → configurazione → completamento</li>
        <li>Elimina <code>installer.php</code> e il ZIP per sicurezza</li>
    </ol>

    <div class="warn"><strong>Attenzione:</strong> Il ripristino sovrascrive tutti i dati attuali!</div>
</div>

<!-- 13. IMPOSTAZIONI -->
<div class="guide-section" id="settings">
    <h2>13. Impostazioni e SEO</h2>

    <h3>Generale</h3>
    <p>Nome sito, descrizione, URL base, email admin, modalità manutenzione.</p>

    <h3>SEO</h3>
    <ul>
        <li><strong>Sitemap.xml</strong> generata automaticamente al salvataggio</li>
        <li><strong>robots.txt</strong> editabile</li>
        <li><strong>Meta title/description</strong> per ogni pagina e articolo</li>
    </ul>

    <h3>Code Injection</h3>
    <p>Inserisci codice personalizzato nel <code>&lt;head&gt;</code> (analytics, meta tag) o prima di <code>&lt;/body&gt;</code> (chat widget, script).</p>
</div>

<!-- 14. API REST -->
<div class="guide-section" id="api">
    <h2>14. API REST</h2>
    <p>O-CMS espone API REST JSON complete per gestire i contenuti da applicazioni esterne.</p>

    <h3>Autenticazione</h3>
    <p>Genera un token personale dalla sezione <strong>API</strong> nel menu. Ogni token eredita i permessi del tuo ruolo. Invia il token come header:</p>
    <pre style="background:var(--bg);padding:10px;border-radius:8px;font-size:0.85rem;"><code>Authorization: Bearer IL_TUO_TOKEN</code></pre>

    <h3>Endpoint per ruolo</h3>
    <p><strong>Registrato+</strong>: lettura pagine, articoli, categorie, menu, media, config</p>
    <p><strong>Publisher+</strong>: CRUD articoli (propri; editor+ tutti)</p>
    <p><strong>Editor+</strong>: CRUD pagine e categorie</p>
    <p><strong>Amministratore+</strong>: lettura utenti</p>
    <p><strong>Super Amministratore</strong>: lettura/scrittura impostazioni</p>
    <p>La documentazione completa con tutti gli endpoint, body JSON e permessi è disponibile in <a href="<?= ocms_base_url() ?>/admin/api-docs">API</a> nel menu.</p>
</div>

<!-- 15. SICUREZZA -->
<div class="guide-section" id="security">
    <h2>15. Sicurezza</h2>
    <ul>
        <li>Password hashate con <strong>bcrypt</strong></li>
        <li>Protezione <strong>CSRF</strong> su tutti i form</li>
        <li>Cartella <code>data/</code> protetta da <strong>.htaccess</strong> (deny all)</li>
        <li>Sessioni con rigenerazione ID dopo login</li>
        <li>Sanitizzazione input con <code>htmlspecialchars</code></li>
        <li>Nomi file upload sanitizzati con slug + random</li>
        <li>Path traversal prevention su ZIP e upload</li>
    </ul>

    <div class="warn"><strong>Importante:</strong> Elimina <code>install.php</code> dopo la prima installazione. Cambia le credenziali predefinite. Mantieni il CMS aggiornato.</div>
</div>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
