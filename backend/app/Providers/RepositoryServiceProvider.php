<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\KeywordRepositoryInterface;
use App\Repositories\KeywordRepository;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Repositories\ArticleRepository;
use App\Repositories\Contracts\WordPressSiteRepositoryInterface;
use App\Repositories\WordPressSiteRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KeywordRepositoryInterface::class, KeywordRepository::class);
        $this->app->bind(ArticleRepositoryInterface::class, ArticleRepository::class);
        $this->app->bind(WordPressSiteRepositoryInterface::class, WordPressSiteRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
