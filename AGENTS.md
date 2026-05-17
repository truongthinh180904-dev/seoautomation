# AI Engineering Rules

## Core Rules

* Never delete important business logic
* Never remove environment configurations
* Never remove API contracts
* Never modify database schema without explanation
* Never break existing features
* Only clean unused/dead code safely
* Preserve project architecture
* Preserve strict typing
* Always prefer reusable components
* Always maintain scalability
* Always keep code modular
* Always keep APIs backward compatible

---

# Frontend Rules

Stack:

* NextJS App Router
* TypeScript strict
* TailwindCSS
* shadcn/ui

Rules:

* Use feature-based folder structure
* Use reusable UI components
* Avoid duplicated UI logic
* Use React Hook Form
* Use Zod validation
* Use TanStack Query
* Use server actions carefully
* Use loading/skeleton states
* Optimize rendering
* Avoid unnecessary rerenders
* Use clean responsive design
* Use typed API responses

---

# Backend Rules

Stack:

* Laravel 12
* PostgreSQL
* Redis
* Queue/Horizon

Rules:

* Use Service pattern
* Use Repository pattern
* Use Form Requests validation
* Use API Resources
* Use DTO where needed
* Use Jobs for heavy processing
* Use queue retry strategy
* Use event/listener architecture
* Keep controllers thin
* Keep business logic in services
* Never place logic directly in controllers
* Use caching carefully
* Use transactions where needed

---

# AI Workflow Rules

* Separate AI agents by responsibility
* Avoid giant prompts
* Use multi-step generation
* Validate AI outputs
* Store AI logs
* Retry failed generations
* Preserve prompt versioning
* Support multiple AI providers

---

# SEO Rules

* Avoid duplicate content
* Avoid keyword stuffing
* Optimize semantic SEO
* Generate internal links
* Optimize headings structure
* Generate FAQ schema
* Preserve readability
* Humanize AI content

---

# Database Rules

* Use indexing carefully
* Avoid N+1 queries
* Use migrations safely
* Never drop production tables automatically
* Preserve data integrity

---

# DevOps Rules

* Never expose secrets
* Use .env properly
* Preserve deployment configs
* Maintain CI/CD compatibility
* Keep builds stable

---

# Cleanup Rules

Allowed:

* remove dead code
* remove unused imports
* remove duplicated code
* optimize performance

Not allowed:

* remove core business logic
* remove API endpoints
* remove queue logic
* remove integrations
* remove environment configs
