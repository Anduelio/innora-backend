<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PassportSetupCommand extends Command
{
    protected $signature = 'passport:setup
        {--force : Skip confirmation when rotating existing clients/keys}';

    protected $description = 'Generate Passport keys, create the password-grant client, and write its credentials to .env.';

    public function handle(): int
    {
        if (! file_exists(base_path('.env'))) {
            $this->error('.env file not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! app()->environment('local', 'testing')) {
            if (! $this->confirm('This will rotate Passport keys/clients and update .env. Continue?', false)) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        $this->components->info('Setting up Passport…');

        $this->step('Generating encryption keys', function (): void {
            Artisan::call('passport:keys', ['--force' => true], $this->output);
        });

        $this->step('Creating password-grant client', function (): void {
            $this->createPasswordClient();
        });

        $this->step('Clearing config cache', function (): void {
            Artisan::call('config:clear');
        });

        $this->newLine();
        $this->components->twoColumnDetail('PASSPORT_CLIENT_ID', (string) env('PASSPORT_CLIENT_ID', '—'));
        $this->components->info('Passport client written to .env.');

        return self::SUCCESS;
    }

    private function step(string $label, \Closure $action): void
    {
        $this->components->task($label, function () use ($action): bool {
            $action();

            return true;
        });
    }

    private function createPasswordClient(): void
    {
        Artisan::call('passport:client', [
            '--password' => true,
            '--name' => 'Innora Password Client',
            '--provider' => 'users',
        ]);

        [$clientId, $clientSecret] = $this->parseClientCredentials(Artisan::output());

        $this->setEnvValue('PASSPORT_CLIENT_ID', $clientId);
        $this->setEnvValue('PASSPORT_CLIENT_SECRET', $clientSecret);

        putenv("PASSPORT_CLIENT_ID={$clientId}");
        putenv("PASSPORT_CLIENT_SECRET={$clientSecret}");
        $_ENV['PASSPORT_CLIENT_ID'] = $clientId;
        $_ENV['PASSPORT_CLIENT_SECRET'] = $clientSecret;
        $_SERVER['PASSPORT_CLIENT_ID'] = $clientId;
        $_SERVER['PASSPORT_CLIENT_SECRET'] = $clientSecret;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseClientCredentials(string $output): array
    {
        preg_match('/Client ID[\s.]+([^\s]+)/i', $output, $idMatch);
        preg_match('/Client [Ss]ecret[\s.]+([^\s]+)/', $output, $secretMatch);

        $clientId = $idMatch[1] ?? null;
        $clientSecret = $secretMatch[1] ?? null;

        if ($clientId === null || $clientSecret === null || $clientId === '' || $clientSecret === '') {
            throw new \RuntimeException(
                "Could not parse Passport client ID/secret from `passport:client` output:\n\n".$output,
            );
        }

        return [$clientId, $clientSecret];
    }

    private function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        if ($content === false) {
            throw new \RuntimeException('Unable to read .env.');
        }

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content = rtrim($content)."\n\n{$key}={$value}\n";
        }

        if (file_put_contents($envPath, $content) === false) {
            throw new \RuntimeException("Unable to write {$key} to .env.");
        }
    }
}
