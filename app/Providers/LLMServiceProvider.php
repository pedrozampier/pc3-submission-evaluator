<?php

namespace App\Providers;

use App\Services\LLM\AnthropicService;
use App\Services\LLM\LLMServiceInterface;
use App\Services\LLM\OpenAIService;
use Illuminate\Support\ServiceProvider;

class LLMServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(LLMServiceInterface::class, function ($app) {
            $provider = config('services.llm_provider', 'anthropic');

            return match ($provider) {
                'openai' => new OpenAIService(),
                'anthropic' => new AnthropicService(),
                default => new AnthropicService(),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
