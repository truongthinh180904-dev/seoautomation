<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Repositories\KeywordRepository;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\ArticleRepository;
use App\Repositories\Contracts\WordPressSiteRepositoryInterface;
use App\Repositories\WordPressSiteRepository;
use App\Repositories\Contracts\AIPromptVersionRepositoryInterface;
use App\Repositories\AIPromptVersionRepository;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Repositories\ScheduleRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KeywordRepositoryInterface::class, KeywordRepository::class);
        $this->app->bind(ArticleRepositoryInterface::class, ArticleRepository::class);
        $this->app->bind(WordPressSiteRepositoryInterface::class, WordPressSiteRepository::class);
        $this->app->bind(AIPromptVersionRepositoryInterface::class, AIPromptVersionRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
