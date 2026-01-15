<?php

namespace Twilio\WebhookCli;

use Dotenv\Dotenv;
use Exception;

class Command
{
    public function __construct(
        private array $argv
    ) {
        $this->loadEnvFile();
    }

    private function loadEnvFile(): void
    {
        $envFile = getcwd() . '/.env';
        if (!file_exists($envFile)) {
            return;
        }

        $dotenv = Dotenv::createImmutable(getcwd());
        $dotenv->load();
    }

    private function parseArguments(): array
    {
        $args = [
            'url' => null,
            'auth_token' => null,
            'account_sid' => null,
            'api_key' => null,
            'api_secret' => null,
            'method' => 'POST',
            'data' => [],
            'no_signature' => false,
            'debug' => false,
            'insecure' => false,
        ];

        for ($i = 1; $i < count($this->argv); $i++) {
            $arg = $this->argv[$i];
            
            switch ($arg) {
                case '--auth-token':
                case '-a':
                    $args['auth_token'] = $this->argv[++$i] ?? null;
                    break;
                    
                case '--account-sid':
                    $args['account_sid'] = $this->argv[++$i] ?? null;
                    break;
                    
                case '--api-key':
                    $args['api_key'] = $this->argv[++$i] ?? null;
                    break;
                    
                case '--api-secret':
                    $args['api_secret'] = $this->argv[++$i] ?? null;
                    break;
                    
                case '--method':
                case '-X':
                    $args['method'] = $this->argv[++$i] ?? 'POST';
                    break;
                    
                case '--data-urlencode':
                case '-d':
                    $data = $this->argv[++$i] ?? '';
                    if ($data && str_contains($data, '=')) {
                        [$key, $value] = explode('=', $data, 2);
                        $args['data'][$key] = $value;
                    }
                    break;
                    
                case '--no-signature':
                    $args['no_signature'] = true;
                    break;
                    
                case '--insecure':
                case '--allow-self-signed':
                    $args['insecure'] = true;
                    break;
                    
                case '-l':
                    $value = $this->argv[++$i] ?? null;
                    if ($value === 'debug') {
                        $args['debug'] = true;
                    }
                    break;
                    
                default:
                    if (!$args['url'] && !str_starts_with($arg, '-')) {
                        $args['url'] = $arg;
                    }
            }
        }

        $args['url'] ??= $_ENV['WEBHOOK_URL'] ?? getenv('WEBHOOK_URL') ?: null;
        $args['auth_token'] ??= $_ENV['TWILIO_AUTH_TOKEN'] ?? getenv('TWILIO_AUTH_TOKEN') ?: null;
        $args['account_sid'] ??= $_ENV['TWILIO_ACCOUNT_SID'] ?? getenv('TWILIO_ACCOUNT_SID') ?: null;
        $args['api_key'] ??= $_ENV['TWILIO_API_KEY'] ?? getenv('TWILIO_API_KEY') ?: null;
        $args['api_secret'] ??= $_ENV['TWILIO_API_SECRET'] ?? getenv('TWILIO_API_SECRET') ?: null;

        return $args;
    }

    public function run(): int
    {
        try {
            $args = $this->parseArguments();

            if (!$args['url']) {
                fwrite(STDERR, "\nError: Webhook URL is required\n\n");
                return 1;
            }

            if (!filter_var($args['url'], FILTER_VALIDATE_URL)) {
                fwrite(STDERR, "\nError: Invalid webhook URL\n\n");
                return 1;
            }

            if (!in_array(strtoupper($args['method']), ['GET', 'POST'], true)) {
                fwrite(STDERR, "\nError: Method must be GET or POST\n\n");
                return 1;
            }

            // Validate phone numbers in data if present
            if (isset($args['data']['From'])) {
                PhoneNumberValidator::validateE164($args['data']['From'], 'From');
            }
            if (isset($args['data']['To'])) {
                PhoneNumberValidator::validateE164($args['data']['To'], 'To');
            }

            $invoker = new WebhookInvoker($args['url'], $args['auth_token'], $args['no_signature']);
            
            if ($args['account_sid']) {
                $invoker->setAccountSid($args['account_sid']);
            }
            
            if ($args['api_key']) {
                $invoker->setApiKey($args['api_key']);
            }
            
            if ($args['api_secret']) {
                $invoker->setApiSecret($args['api_secret']);
            }
            
            if ($args['debug']) {
                $invoker->setDebug(true);
            }
            
            if ($args['insecure']) {
                $invoker->setInsecure(true);
            }
            
            $invoker->setMethod($args['method'])->setData($args['data']);

            $result = $invoker->invoke();

            if ($args['debug']) {
                // In debug mode, output exactly as received without modification
                echo $result['body'];
                return $result['success'] ? 0 : $result['status_code'];
            }

            echo "\n" . $result['body'] . "\n\n";
            return 0;

        } catch (Exception $e) {
            fwrite(STDERR, "\nError: " . $e->getMessage() . "\n\n");
            return 1;
        }
    }
}
