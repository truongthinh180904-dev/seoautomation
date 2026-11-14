<?php

namespace App\Events;

use App\Models\SeoIntelligenceInsight;
use App\Models\WebsiteAutomationRule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeoAnomalyDetectedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public SeoIntelligenceInsight $insight,
        public ?WebsiteAutomationRule $rule = null,
        public ?string $thresholdBreached = null
    ) {}
}
