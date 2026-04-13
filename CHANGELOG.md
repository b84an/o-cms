# Changelog

All notable changes to O-CMS will be documented in this file.

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
