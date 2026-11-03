# Task Test Report: TASK-06

- **RUN_ID**: RUN_20261103_162119_TASK-06_R2
- **Task ID**: TASK-06
- **Timestamp Start**: 2026-11-03 17:04:52
- **Timestamp End**: 2026-11-03 17:06:02
- **Command Executed**: `cd backend && (composer dump-autoload -q || true) && ./vendor/bin/pint --test && php artisan test --filter=PipelineTest`
- **Exit Code**: 0
- **OVERALL_TEST_RESULT**: PASS

## Output Summary (Last 120 lines)
```text

  .......................................................................................
  .......................................................................................
  .......................................................................................
  .......................................................................................
  ...........

  ─────────────────────────────────────────────────────────────────────────────── Laravel  
    PASS   .................................................................... 359 files  


  [30;42;1m PASS [39;49;22m[39m Tests\Feature\PipelineTest[39m
  [32;1m✓[39;22m[90m [39m[90mkeyword import parses serial date applies anti idor and records imp[39m[90m…[39m [90m3.98s[39m  
  [32;1m✓[39;22m[90m [39m[90mcampaign start dispatches keyword batch jobs and creates task[39m[90m       [39m [90m0.38s[39m  
  [32;1m✓[39;22m[90m [39m[90mcampaign pause and resume cancels and restarts tasks[39m[90m                [39m [90m0.20s[39m  
  [32;1m✓[39;22m[90m [39m[90mjob dispatcher ignores draft campaign keywords without schedule[39m[90m     [39m [90m0.47s[39m  
  [32;1m✓[39;22m[90m [39m[90mai provider failover mechanism from openai to anthropic[39m[90m             [39m [90m0.71s[39m  
  [32;1m✓[39;22m[90m [39m[90mrate limit exception constructor compatibility[39m[90m                      [39m [90m0.33s[39m  
  [32;1m✓[39;22m[90m [39m[90minternal linking service handles encrypted app password without cra[39m[90m…[39m [90m0.58s[39m  
  [32;1m✓[39;22m[90m [39m[90mcompetitor crawl service blocks ssrf and counts vietnamese words[39m[90m    [39m [90m0.48s[39m  
  [32;1m✓[39;22m[90m [39m[90mimage downloader blocks private ip ssrf[39m[90m                             [39m [90m0.60s[39m  
  [32;1m✓[39;22m[90m [39m[90mduplicate detection is scoped to workspace and does not leak titles[39m[90m [39m [90m0.45s[39m  
  [32;1m✓[39;22m[90m [39m[90marticle pipeline service concurrency locking and cancel state[39m[90m       [39m [90m0.47s[39m  
  [32;1m✓[39;22m[90m [39m[90mtask service lifecycle reserves and releases credits on cancel[39m[90m      [39m [90m0.42s[39m  
  [32;1m✓[39;22m[90m [39m[90mpublish to wordpress embeds faq schema and rethrows exception for r[39m[90m…[39m [90m0.34s[39m  
  [32;1m✓[39;22m[90m [39m[90mgemini provider handles 404 fallback and 429 ratelimit[39m[90m              [39m [90m1.22s[39m  
  [32;1m✓[39;22m[90m [39m[90manthropic provider catches connection exception and throws provider[39m[90m…[39m [90m0.20s[39m  
  [32;1m✓[39;22m[90m [39m[90mpublish to wordpress job blocks cross tenant site lookup[39m[90m            [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mcampaign pause resets processing keywords to pending and resume pic[39m[90m…[39m [90m0.37s[39m  
  [32;1m✓[39;22m[90m [39m[90marticle generate endpoint uses task service and reserves credits[39m[90m    [39m [90m0.52s[39m  
  [32;1m✓[39;22m[90m [39m[90mtask controller validations and thin controller[39m[90m                     [39m [90m0.72s[39m  
  [32;1m✓[39;22m[90m [39m[90mupload to wordpress media job blocks cross tenant and cross workspa[39m[90m…[39m [90m0.29s[39m  
  [32;1m✓[39;22m[90m [39m[90marticle generate images enforces credits before dispatch[39m[90m            [39m [90m0.26s[39m  
  [32;1m✓[39;22m[90m [39m[90mkeyword serp endpoint enforces credits and workspace quota[39m[90m          [39m [90m0.36s[39m  
  [32;1m✓[39;22m[90m [39m[90mdownload image job and upload to wordpress media job happy path cle[39m[90m…[39m [90m0.38s[39m  
  [32;1m✓[39;22m[90m [39m[90mcross workspace idor protection in article and keyword controllers[39m[90m  [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mserp research job reserves credits or aborts when zero credits[39m[90m      [39m [90m0.33s[39m  
  [32;1m✓[39;22m[90m [39m[90mcampaign service pause cancels pending tasks and releases credits[39m[90m   [39m [90m0.24s[39m  

  [90mTests:[39m    [32;1m26 passed[39;22m[90m (111 assertions)[39m
  [90mDuration:[39m [39m15.49s[39m
```
