<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * SDK adapter for terminals requiring the vendor's encrypted handshake.
 * Each request owns a short-lived SDK connection and disconnects in finally.
 * Uses the locally registered 32-bit SDK; vendor binaries are not redistributed.
 */
class ZKTecoSdkService
{
    public function __construct(
        private readonly string $ip,
        private readonly int $port,
        private readonly int $timeout,
        private readonly string $password,
    ) {}

    public function request(string $operation, array $parameters = []): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('This device requires an encrypted SDK connection. The fallback requires Windows and the registered ZKTeco SDK.');
        }

        if (!in_array($operation, ['info', 'attendance', 'enroll_fingerprint', 'enroll_face', 'fingerprint_template', 'cancel_enrollment'], true)) {
            throw new RuntimeException('Unsupported ZKTeco SDK operation.');
        }

        $windows = getenv('SystemRoot') ?: 'C:\\Windows';
        $powershell = $windows . '\\SysWOW64\\WindowsPowerShell\\v1.0\\powershell.exe';
        if (!is_file($powershell)) {
            $powershell = $windows . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        }

        $scriptPath = realpath(__DIR__ . '/../../resources/scripts/zkteco-sdk.ps1');
        if ($scriptPath === false) {
            throw new RuntimeException('The ZKTeco SDK bridge script is missing.');
        }

        // Keep the launcher below Windows command-length limits. Encode only
        // a trusted local path. Device values and the Comm Key go over
        // stdin, never into shell commands or the process command line.
        $launcher = "& ([scriptblock]::Create([IO.File]::ReadAllText('"
            . str_replace("'", "''", $scriptPath) . "')))";
        $process = new Process([
            $powershell, '-NoProfile', '-NonInteractive', '-EncodedCommand',
            base64_encode(mb_convert_encoding($launcher, 'UTF-16LE', 'UTF-8')),
        ], null, $this->processEnvironment($windows));
        $process->setInput(json_encode([
            'operation' => $operation,
            'ip' => $this->ip,
            'port' => $this->port,
            'password' => $this->password,
            'parameters' => $parameters,
        ], JSON_THROW_ON_ERROR));
        $process->setTimeout($operation === 'attendance' ? max(120, $this->timeout) : max(20, $this->timeout));
        $process->run();

        if (!$process->isSuccessful() && trim($process->getOutput()) === '') {
            $error = $process->getErrorOutput();
            // PowerShell startup failures can be UTF-16LE even though the
            // bridge itself writes UTF-8 (the script has not started yet).
            if (str_contains($error, "\x00")) {
                $error = mb_convert_encoding($error, 'UTF-8', 'UTF-16LE');
            }
            $error = trim(preg_replace('/\s+/', ' ', $error) ?? '');
            throw new RuntimeException(sprintf(
                'The ZKTeco SDK bridge could not start (exit %s). %s',
                $process->getExitCode(),
                $error !== '' ? mb_substr($error, 0, 500) : 'PowerShell returned no diagnostic output.'
            ));
        }

        try {
            $response = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('The ZKTeco SDK bridge did not return a valid response. Verify that the 32-bit zkemkeeper SDK is installed and registered.', 0, $e);
        }

        if (!$process->isSuccessful() || !is_array($response) || ($response['ok'] ?? false) !== true) {
            throw new RuntimeException('ZKTeco SDK: ' . ($response['error'] ?? 'Connection failed.'));
        }

        if (!is_array($response['data'] ?? null)) {
            throw new RuntimeException('The ZKTeco SDK bridge returned an invalid data structure.');
        }

        return $response['data'];
    }

    private function processEnvironment(string $windows): array
    {
        // In HTTP requests Symfony intersects getenv() with $_SERVER. After
        // Laravel boots, that can discard Windows variables required by .NET,
        // making PowerShell fail with 8009001d before it executes the bridge.
        // Supply the actual OS environment explicitly, just for this child.
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        $keys = array_change_key_case($environment, CASE_LOWER);

        foreach (['SystemRoot' => $windows, 'windir' => $windows] as $key => $value) {
            if (empty($keys[strtolower($key)])) {
                foreach (array_keys($environment) as $existingKey) {
                    if (strcasecmp($existingKey, $key) === 0) {
                        unset($environment[$existingKey]);
                    }
                }
                $environment[$key] = $value;
            }
        }

        return $environment;
    }
}
