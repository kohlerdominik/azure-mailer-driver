<?php

namespace Hafael\Mailer\Azure;

use Illuminate\Support\ServiceProvider;

class AzureMailerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $manager = $this->app->make('mail.manager');

        $manager->extend('acs', fn (array $config) => $this->makeTransport($config));

        // Legacy 'azure' transport — registers a lazy callback that maps old config keys to the
        // new format. The transport is only instantiated if legacy 'transport: azure' is actually used.
        $manager->extend('azure', fn (array $config) => $this->makeTransport($this->normalizeLegacyConfig($config)));
    }

    private function makeTransport(array $config): AzureTransport
    {
        return new AzureTransport(
            $config['key'] ?? $this->app['config']->get('services.acs.key'),
            $config['endpoint'] ?? $this->app['config']->get('services.acs.endpoint'),
            $config['disable_tracking'] ?? false,
            $config['api_version'] ?? '2023-03-31',
        );
    }

    /**
     * Maps old 'azure' transport config keys to the current format:
     *   access_key       → key
     *   resource_name    → endpoint (https://{resource_name}.communication.azure.com)
     *   disable_user_tracking → disable_tracking
     */
    private function normalizeLegacyConfig(array $config): array
    {
        if (! isset($config['key']) && isset($config['access_key'])) {
            $config['key'] = $config['access_key'];
        }

        if (! isset($config['endpoint']) && isset($config['resource_name'])) {
            $name = $config['resource_name'];
            $config['endpoint'] = str_contains($name, '.')
                ? 'https://'.ltrim($name, '/')
                : "https://{$name}.communication.azure.com";
        }

        if (! isset($config['disable_tracking']) && isset($config['disable_user_tracking'])) {
            $config['disable_tracking'] = (bool) $config['disable_user_tracking'];
        }

        return $config;
    }
}
