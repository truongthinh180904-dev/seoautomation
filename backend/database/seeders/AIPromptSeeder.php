<?php

namespace Database\Seeders;

use App\Enums\AgentType;
use App\Models\AIPromptVersion;
use Illuminate\Database\Seeder;

class AIPromptSeeder extends Seeder
{
    public function run(): void
    {
        $prompts = [
            [
                'agent_type' => AgentType::SERP_RESEARCH->value,
                'name' => 'SERP Research Default',
                'system_prompt' => 'You are an expert SEO researcher analyzing Google search results.',
                'user_prompt_template' => "Keyword: {{keyword}}\nSERP Data: {{serp_data}}\nExtract the main search intent and top topics.",
                'variables' => ['keyword', 'serp_data'],
            ],
            [
                'agent_type' => AgentType::COMPETITOR_ANALYSIS->value,
                'name' => 'Competitor Analysis Default',
                'system_prompt' => 'You are an SEO competitor analyst.',
                'user_prompt_template' => "Analyze the competitor content for keyword: {{keyword}}.\nContent: {{competitor_content}}",
                'variables' => ['keyword', 'competitor_content'],
            ],
            [
                'agent_type' => AgentType::CONTENT_ANALYSIS->value,
                'name' => 'Content Analysis Default',
                'system_prompt' => 'You are an expert content auditor.',
                'user_prompt_template' => "Analyze the current content for keyword: {{keyword}}.\nContent: {{content}}",
                'variables' => ['keyword', 'content'],
            ],
            [
                'agent_type' => AgentType::OUTLINE->value,
                'name' => 'Outline Generation Default',
                'system_prompt' => 'You are a master copywriter creating highly engaging outlines.',
                'user_prompt_template' => "Create a Vietnamese SEO article outline for keyword: {{keyword}}\nSearch Intent: {{search_intent}}\nTarget word count: {{word_count_target}}\nCompetitor insights:\n{{competitors_summary}}\n\nReturn ONLY valid JSON with this exact structure: {\"h1\":\"string containing the keyword\",\"sections\":[{\"heading\":\"string\",\"points\":[\"string\"]}],\"faqs\":[{\"question\":\"string\",\"answer\":\"string\"}]}. Include at least 4 sections.",
                'variables' => ['keyword', 'search_intent', 'word_count_target', 'competitors_summary'],
            ],
            [
                'agent_type' => AgentType::WRITING->value,
                'name' => 'Article Writing Default',
                'system_prompt' => 'You are a top-tier SEO copywriter writing in Vietnamese.',
                'user_prompt_template' => "Write a human, helpful Vietnamese SEO article for keyword: {{keyword}}\nOutline JSON:\n{{outline_json}}\nSemantic keywords: {{semantic_keywords}}\nEntities: {{entities}}\nFAQs to answer: {{faqs_to_answer}}\n\nReturn ONLY valid JSON with this exact structure: {\"title\":\"string containing the keyword\",\"content\":\"HTML article content longer than 1200 words\",\"excerpt\":\"short summary\"}.",
                'variables' => ['keyword', 'outline_json', 'semantic_keywords', 'entities', 'faqs_to_answer'],
            ],
            [
                'agent_type' => AgentType::SEO_OPTIMIZATION->value,
                'name' => 'SEO Optimization Default',
                'system_prompt' => 'You are an On-page SEO specialist.',
                'user_prompt_template' => "Optimize the following article for the keyword: {{keyword}}.\nArticle: {{article_content}}",
                'variables' => ['keyword', 'article_content'],
            ],
            [
                'agent_type' => AgentType::INTERNAL_LINKING->value,
                'name' => 'Internal Linking Default',
                'system_prompt' => 'You are an SEO internal linking expert.',
                'user_prompt_template' => "Suggest internal links for this article from the list of available URLs.\nArticle: {{article_content}}\nURLs: {{urls}}",
                'variables' => ['article_content', 'urls'],
            ],
            [
                'agent_type' => AgentType::QA_VALIDATION->value,
                'name' => 'QA Validation Default',
                'system_prompt' => 'You are an editor QA validation specialist.',
                'user_prompt_template' => "Check the article for quality, readability, and factual correctness.\nArticle: {{article_content}}",
                'variables' => ['article_content'],
            ],
            [
                'agent_type' => AgentType::PUBLISHING->value,
                'name' => 'Publishing Format Default',
                'system_prompt' => 'You are a formatting expert for WordPress HTML.',
                'user_prompt_template' => "Format the article into clean HTML for WordPress.\nArticle: {{article_content}}",
                'variables' => ['article_content'],
            ],
        ];

        $admin = \App\Models\User::first();

        foreach ($prompts as $prompt) {
            AIPromptVersion::updateOrCreate(
                [
                    'agent_type' => $prompt['agent_type'],
                    'is_default' => true,
                    'tenant_id' => null,
                ],
                [
                    'version' => '1.0.0',
                    'name' => $prompt['name'],
                    'system_prompt' => $prompt['system_prompt'],
                    'user_prompt_template' => $prompt['user_prompt_template'],
                    'variables' => $prompt['variables'],
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'created_at' => now(),
                ]
            );
        }
    }
}
