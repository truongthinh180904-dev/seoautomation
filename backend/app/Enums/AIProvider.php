<?php

namespace App\Enums;

enum AIProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GEMINI = 'gemini';
    case DEEPSEEK = 'deepseek';

    public function label(): string
    {
        return match($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic',
            self::GEMINI => 'Google Gemini',
            self::DEEPSEEK => 'DeepSeek',
        };
    }

    public function configKey(): string
    {
        return match($this) {
            self::OPENAI => 'ai.providers.openai',
            self::ANTHROPIC => 'ai.providers.anthropic',
            self::GEMINI => 'ai.providers.gemini',
            self::DEEPSEEK => 'ai.providers.deepseek',
        };
    }
}
