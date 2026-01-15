<?php

namespace Twilio\WebhookCli;

use Exception;
use Faker\Factory;
use Faker\Generator;

class WebhookInvoker
{
    private string $method = 'POST';
    private array $data = [];
    private ?string $accountSid = null;
    private ?string $apiKey = null;
    private ?string $apiSecret = null;
    private string $twilioCliPath;

    public function __construct(
        private string $url,
        private ?string $authToken = null,
        private bool $noSignature = false
    ) {
        $this->twilioCliPath = trim(shell_exec('which twilio 2>/dev/null') ?: '');
        
        if (empty($this->twilioCliPath)) {
            throw new Exception("Twilio CLI not found. Install: https://www.twilio.com/docs/twilio-cli/quickstart");
        }
    }

    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function setData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function setAccountSid(string $sid): self
    {
        $this->accountSid = $sid;
        return $this;
    }

    public function setApiKey(string $key): self
    {
        $this->apiKey = $key;
        return $this;
    }

    public function setApiSecret(string $secret): self
    {
        $this->apiSecret = $secret;
        return $this;
    }

    public function invoke(): array
    {
        $faker = Factory::create('en_US');
        
        $this->ensureMessageSids();
        $this->ensurePhoneNumbers($faker);
        $this->ensureBody($faker);
        $this->ensureAccountSids();
        $this->ensureLocationData($faker);
        $this->ensureAdditionalFields($faker);
        
        $command = $this->buildCommand();
        $env = $this->buildEnvironment();
        
        return $this->executeCommand($command, $env);
    }

    private function ensureMessageSids(): void
    {
        if (!isset($this->data['MessageSid'])) {
            $messageSid = 'SM' . bin2hex(random_bytes(16));
            $this->data['MessageSid'] = $messageSid;
            $this->data['SmsMessageSid'] = $messageSid;
            $this->data['SmsSid'] = $messageSid;
        } else {
            $messageSid = $this->data['MessageSid'];
            $this->data['SmsMessageSid'] ??= $messageSid;
            $this->data['SmsSid'] ??= $messageSid;
        }
    }

    private function ensurePhoneNumbers(Generator $faker): void
    {
        $this->data['From'] ??= getenv('TWILIO_FROM') ?: '+1' . $faker->numerify('5#########');
        $this->data['To'] ??= getenv('TWILIO_TO') ?: '+1' . $faker->numerify('5#########');
    }

    private function ensureBody(Generator $faker): void
    {
        $this->data['Body'] ??= $faker->realText(rand(100, 160));
    }

    private function ensureAccountSids(): void
    {
        $this->data['AccountSid'] ??= 'AC' . bin2hex(random_bytes(16));
        $this->data['MessagingServiceSid'] ??= 'MG' . bin2hex(random_bytes(16));
    }

    private function ensureLocationData(Generator $faker): void
    {
        $this->data['FromCountry'] ??= 'US';
        $this->data['FromState'] ??= $faker->stateAbbr();
        $this->data['FromCity'] ??= $faker->city();
        $this->data['FromZip'] ??= $faker->postcode();
        
        $this->data['ToCountry'] ??= 'US';
        $this->data['ToState'] ??= $faker->stateAbbr();
        $this->data['ToCity'] ??= $faker->city();
        $this->data['ToZip'] ??= $faker->postcode();
    }

    private function ensureAdditionalFields(Generator $faker): void
    {
        $this->data['NumMedia'] ??= '0';
        $this->data['SmsStatus'] ??= $faker->randomElement(['received', 'sent', 'delivered']);
    }

    private function buildCommand(): array
    {
        $command = [
            $this->twilioCliPath,
            'webhook:invoke',
            $this->url,
            '--type',
            'sms'
        ];
        
        if ($this->method === 'GET') {
            $command[] = '--method';
            $command[] = 'GET';
        }
        
        if ($this->authToken) {
            $command[] = '--auth-token';
            $command[] = $this->authToken;
        }
        
        if ($this->noSignature) {
            $command[] = '--no-signature';
        }
        
        foreach ($this->data as $key => $value) {
            $command[] = '--data-urlencode';
            $command[] = "$key=$value";
        }
        
        return $command;
    }

    private function buildEnvironment(): array
    {
        $env = $_ENV ?: [];
        
        $systemVars = ['PATH', 'HOME', 'USER', 'SHELL'];
        foreach ($systemVars as $var) {
            $value = getenv($var);
            if ($value !== false) {
                $env[$var] = $value;
            }
        }
        
        $twilioVars = ['TWILIO_ACCOUNT_SID', 'TWILIO_API_KEY', 'TWILIO_API_SECRET', 'TWILIO_AUTH_TOKEN'];
        foreach ($twilioVars as $var) {
            $value = getenv($var);
            if ($value !== false) {
                $env[$var] = $value;
            }
        }
        
        if ($this->accountSid) {
            $env['TWILIO_ACCOUNT_SID'] = $this->accountSid;
        }
        
        if ($this->apiKey) {
            $env['TWILIO_API_KEY'] = $this->apiKey;
        }
        
        if ($this->apiSecret) {
            $env['TWILIO_API_SECRET'] = $this->apiSecret;
        }
        
        if ($this->authToken) {
            $env['TWILIO_AUTH_TOKEN'] = $this->authToken;
        }
        
        return $env;
    }

    private function executeCommand(array $command, array $env): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, null, $env);
        
        if (!is_resource($process)) {
            throw new Exception("Failed to execute Twilio CLI command.");
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        
        if ($exitCode !== 0) {
            $errorMessage = $error ?: "Exit code: $exitCode";
            throw new Exception($this->formatError($errorMessage));
        }

        return [
            'status_code' => 200,
            'body' => trim($output),
            'success' => true
        ];
    }

    private function formatError(string $error): string
    {
        $err = trim($error);
        
        if ($this->isNodeJsError($err)) {
            return "Node.js is not installed or not in PATH.\n" .
                   "Install Node.js: brew install node or https://nodejs.org/";
        }
        
        if ($this->isNetworkError($err)) {
            return $this->formatNetworkError($err);
        }
        
        if ($this->isAuthenticationError($err)) {
            return "Twilio CLI authentication failed.\n" .
                   "Set TWILIO_ACCOUNT_SID + (TWILIO_API_KEY + TWILIO_API_SECRET) or TWILIO_AUTH_TOKEN";
        }
        
        if ($this->isSslError($err)) {
            return "SSL/TLS certificate error.\n" .
                   "For local testing, use http:// instead of https://";
        }
        
        if ($this->isTimeoutError($err)) {
            return "Request timed out.\n" .
                   "The webhook server took too long to respond.";
        }
        
        if ($httpError = $this->getHttpError($err)) {
            return $httpError;
        }
        
        return "An error occurred: $err";
    }

    private function isNodeJsError(string $error): bool
    {
        return str_contains($error, 'env: node: No such file') ||
               str_contains($error, 'env: node: command not found') ||
               str_contains($error, 'node: No such file') ||
               str_contains($error, 'node: command not found');
    }

    private function isNetworkError(string $error): bool
    {
        return str_contains($error, 'ENOTFOUND') ||
               str_contains($error, 'getaddrinfo') ||
               str_contains($error, 'ECONNREFUSED') ||
               str_contains($error, 'ENETUNREACH');
    }

    private function formatNetworkError(string $error): string
    {
        $msg = "Unable to connect to the webhook URL.\n";
        $msg .= "Please verify the webhook URL is correct and the server is running.";
        
        if (preg_match('/ENOTFOUND\s+([^\s]+)/', $error, $matches)) {
            $msg .= "\nDomain that failed: " . $matches[1];
        }
        
        return $msg;
    }

    private function isAuthenticationError(string $error): bool
    {
        return str_contains($error, 'Could not find profile') ||
               str_contains($error, 'authentication') ||
               str_contains($error, 'unauthorized');
    }

    private function isSslError(string $error): bool
    {
        return str_contains($error, 'certificate') ||
               str_contains($error, 'SSL') ||
               str_contains($error, 'TLS');
    }

    private function isTimeoutError(string $error): bool
    {
        return str_contains($error, 'timeout') ||
               str_contains($error, 'ETIMEDOUT');
    }

    private function getHttpError(string $error): ?string
    {
        if (!preg_match('/\b(40[0-9]|50[0-9])\b/', $error, $matches)) {
            return null;
        }
        
        $code = $matches[1];
        
        return match ($code) {
            '404' => "Webhook returned HTTP 404 error.\n" .
                     "The webhook endpoint was not found.\n" .
                     "Please verify the webhook URL is correct.",
            '401' => "Webhook returned HTTP 401 error.\n" .
                     "Authentication failed.",
            '403' => "Webhook returned HTTP 403 error.\n" .
                     "Access forbidden.",
            '400' => "Webhook returned HTTP 400 error.\n" .
                     "Bad request. Check request format and parameters.",
            '500' => "Webhook returned HTTP 500 error.\n" .
                     "Internal server error. Check server logs or try again later.",
            '502', '503', '504' => "Webhook returned HTTP {$code} error.\n" .
                                   "Server unavailable. Please try again later.",
            default => $code >= 400 && $code < 500
                ? "Webhook returned HTTP {$code} error.\n" .
                  "Client error. Check request format and parameters."
                : "Webhook returned HTTP {$code} error.\n" .
                  "Server error. Check server logs or try again later."
        };
    }
}
