# Task Test Report: TASK-08

- **RUN_ID**: RUN_20261103_190600_TASK-08_R1
- **Task ID**: TASK-08
- **Timestamp Start**: 2026-11-03 19:30:59
- **Timestamp End**: 2026-11-03 19:31:43
- **Command Executed**: `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test && cd ../frontend && npm run build`
- **Exit Code**: 0
- **OVERALL_TEST_RESULT**: PASS

## Output Summary (Last 120 lines)
```text
  [32;1m✓[39;22m[90m [39m[90mcampaign start dispatches keyword batch jobs and creates task[39m[90m       [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mcampaign pause and resume cancels and restarts tasks[39m[90m                [39m [90m0.06s[39m  
  [32;1m✓[39;22m[90m [39m[90mjob dispatcher ignores draft campaign keywords without schedule[39m[90m     [39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90mai provider failover mechanism from openai to anthropic[39m[90m             [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mrate limit exception constructor compatibility[39m[90m                      [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90minternal linking service handles encrypted app password without cra[39m[90m…[39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompetitor crawl service blocks ssrf and counts vietnamese words[39m[90m    [39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90mimage downloader blocks private ip ssrf[39m[90m                             [39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90mduplicate detection is scoped to workspace and does not leak titles[39m[90m [39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90marticle pipeline service concurrency locking and cancel state[39m[90m       [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mtask service lifecycle reserves and releases credits on cancel[39m[90m      [39m [90m0.07s[39m  
  [32;1m✓[39;22m[90m [39m[90mpublish to wordpress embeds faq schema and rethrows exception for r[39m[90m…[39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90mgemini provider handles 404 fallback and 429 ratelimit[39m[90m              [39m [90m1.05s[39m  
  [32;1m✓[39;22m[90m [39m[90manthropic provider catches connection exception and throws provider[39m[90m…[39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90mpublish to wordpress job blocks cross tenant site lookup[39m[90m            [39m [90m0.06s[39m  
  [32;1m✓[39;22m[90m [39m[90mcampaign pause resets processing keywords to pending and resume pic[39m[90m…[39m [90m0.08s[39m  
  [32;1m✓[39;22m[90m [39m[90marticle generate endpoint uses task service and reserves credits[39m[90m    [39m [90m0.07s[39m  
  [32;1m✓[39;22m[90m [39m[90mtask controller validations and thin controller[39m[90m                     [39m [90m0.08s[39m  
  [32;1m✓[39;22m[90m [39m[90mupload to wordpress media job blocks cross tenant and cross workspa[39m[90m…[39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90marticle generate images enforces credits before dispatch[39m[90m            [39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90mkeyword serp endpoint enforces credits and workspace quota[39m[90m          [39m [90m0.06s[39m  
  [32;1m✓[39;22m[90m [39m[90mdownload image job and upload to wordpress media job happy path cle[39m[90m…[39m [90m0.08s[39m  
  [32;1m✓[39;22m[90m [39m[90mcross workspace idor protection in article and keyword controllers[39m[90m  [39m [90m0.06s[39m  
  [32;1m✓[39;22m[90m [39m[90mserp research job reserves credits or aborts when zero credits[39m[90m      [39m [90m0.09s[39m  
  [32;1m✓[39;22m[90m [39m[90mcampaign service pause cancels pending tasks and releases credits[39m[90m   [39m [90m0.05s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\SecurityAuditTest[39m
  [32;1m✓[39;22m[90m [39m[90msecret credentials and tokens are never exposed via api[39m[90m             [39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90mstrict multi tenant isolation prevents idor across all domain resou[39m[90m…[39m [90m0.06s[39m  
  [32;1m✓[39;22m[90m [39m[90mconcurrency race condition protection on credit deduction with pess[39m[90m…[39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90midempotent admin operations prevent duplicate credit grants[39m[90m         [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mssrf and path traversal payloads are strictly rejected[39m[90m              [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mforeign key and cascade safety in database schema[39m[90m                   [39m [90m0.04s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\TenantIsolationTest[39m
  [32;1m✓[39;22m[90m [39m[90muser a cannot view or update or delete articles of workspace b[39m[90m      [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90muser a cannot view or access campaigns of workspace b[39m[90m               [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90muser a cannot view or manipulate wordpress sites of workspace b[39m[90m     [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90midor prevention on store campaign with foreign wordpress site id[39m[90m    [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90midor prevention on store keyword with foreign campaign or wordpress[39m[90m…[39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90midor prevention on media asset upload to foreign wordpress site[39m[90m     [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90mnon admin cannot access queue status or retry[39m[90m                       [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mwordpress app password is encrypted and never exposed via api[39m[90m       [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mtenant scope isolation in query builder when context is set[39m[90m         [39m [90m0.02s[39m  
  [32;1m✓[39;22m[90m [39m[90muser a cannot view or manipulate websites of workspace b[39m[90m            [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mwebsite creation blocks ssrf urls[39m[90m                                   [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90muser cannot switch to unauthorized workspace[39m[90m                        [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90muser cannot add members to unauthorized workspace[39m[90m                   [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90mkeyword serp endpoint queues job for authorized tenant[39m[90m              [39m [90m0.05s[39m  
  [32;1m✓[39;22m[90m [39m[90muser a cannot view or update or delete keywords of workspace b[39m[90m      [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90midor prevention on generate article with foreign keyword[39m[90m            [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90midor prevention on update article with foreign campaign[39m[90m             [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90muser a cannot view or access workspace b details or members[39m[90m         [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90muser cannot remove members from unauthorized workspace[39m[90m              [39m [90m0.03s[39m  
  [32;1m✓[39;22m[90m [39m[90muser a cannot manipulate media assets of workspace b[39m[90m                [39m [90m0.04s[39m  
  [32;1m✓[39;22m[90m [39m[90msanctum token expiration is strictly configured[39m[90m                     [39m [90m0.04s[39m  

  [90mTests:[39m    [32;1m111 passed[39;22m[90m (872 assertions)[39m
  [90mDuration:[39m [39m6.41s[39m


> frontend@0.1.0 build
> next build

▲ Next.js 16.2.4 (Turbopack)
- Environments: .env.local

⚠ The "middleware" file convention is deprecated. Please use "proxy" instead. Learn more: https://nextjs.org/docs/messages/middleware-to-proxy
  Creating an optimized production build ...
✓ Compiled successfully in 13.1s
  Running TypeScript ...
  Finished TypeScript in 9.3s ...
  Collecting page data using 3 workers ...
  Generating static pages using 3 workers (0/27) ...
  Generating static pages using 3 workers (6/27) 
  Generating static pages using 3 workers (13/27) 
  Generating static pages using 3 workers (20/27) 
✓ Generating static pages using 3 workers (27/27) in 1160ms
  Finalizing page optimization ...

Route (app)
┌ ○ /
├ ○ /_not-found
├ ○ /admin
├ ○ /admin/payment-orders
├ ○ /admin/workspaces
├ ○ /analytics
├ ○ /analytics/usage
├ ○ /articles
├ ƒ /articles/[id]
├ ƒ /articles/[id]/edit
├ ○ /campaigns
├ ƒ /campaigns/[id]
├ ƒ /campaigns/[id]/internal-links
├ ƒ /campaigns/[id]/media
├ ○ /campaigns/create
├ ○ /dashboard
├ ○ /forgot-password
├ ○ /import/wizard
├ ○ /keywords
├ ƒ /keywords/[id]
├ ○ /keywords/import
├ ○ /login
├ ○ /notifications
├ ○ /payment-orders
├ ○ /queue
├ ○ /register
├ ƒ /review/[token]
├ ○ /settings
├ ○ /settings/subscription
├ ○ /websites
├ ○ /wordpress
└ ○ /workspaces


ƒ Proxy (Middleware)

○  (Static)   prerendered as static content
ƒ  (Dynamic)  server-rendered on demand
```
