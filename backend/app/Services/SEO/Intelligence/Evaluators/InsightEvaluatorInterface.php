<?php

namespace App\Services\SEO\Intelligence\Evaluators;

use App\Models\Website;

interface InsightEvaluatorInterface
{
    /**
     * Evaluate data for a given website and return insight candidate blueprints.
     *
     * @return array<int, array<string, mixed>>
     */
    public function evaluate(Website $website): array;
}
