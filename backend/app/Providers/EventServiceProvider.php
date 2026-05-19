<?php

namespace App\Providers;

use App\Events\ArticleApproved;
use App\Events\ArticleGenerated;
use App\Events\ArticlePublished;
use App\Listeners\OnArticleApproved\DispatchPublishingJob;
use App\Listeners\OnArticleGenerated\SendArticleReviewNotification;
use App\Listeners\OnArticlePublished\UpdatePublishingLog;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ArticleGenerated::class => [
            SendArticleReviewNotification::class,
        ],

        ArticleApproved::class => [
            DispatchPublishingJob::class,
        ],

        ArticlePublished::class => [
            UpdatePublishingLog::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
