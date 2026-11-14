<?php

namespace Database\Factories;

use App\Enums\WebsiteStatus;
use App\Models\Website;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    protected $model = Website::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->company().' Blog',
            'domain' => $domain,
            'url' => 'https://'.$domain,
            'default_language' => 'vi',
            'niche' => 'Technology',
            'status' => WebsiteStatus::ACTIVE,
            'settings' => [],
        ];
    }
}
