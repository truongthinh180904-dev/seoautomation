<?php

namespace App\Events;

use App\Models\Article;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Article $article) {}
}
