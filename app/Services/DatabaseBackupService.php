<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseBackupService
{
    private const CIPHER = 'aes-256-cbc';
    private const PBKDF2_ITERATIONS = 200000;
    private const PAYLOAD_VERSION = 1;
    private const BACKUP_MEMORY_LIMIT = '1024M';

    public function createEncryptedServerBackup(string $outputPath, string $passphrase): array
    {
        if (trim($passphrase) === '') {
            throw new RuntimeException('Encryption passphrase is required.');
        }

        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $snapshot = $this->buildSnapshot();
            $encrypted = $this->encryptSnapshot($snapshot, $passphrase);
            if (file_put_contents($outputPath, $encrypted) === false) {
                throw new RuntimeException('Unable to write the encrypted backup file.');
            }
            return ['format' => 'json-v1', 'size_bytes' => strlen($encrypted)];
        }

        $mysqldump = $this->firstExistingBinary([
            env('MYSQLDUMP_PATH'),
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Workbench 8.0 CE\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ]);
        $openssl = $this->firstExistingBinary([
            env('OPENSSL_PATH'),
            'C:\\Program Files\\Git\\mingw64\\bin\\openssl.exe',
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
            'C:\\xampp\\apache\\bin\\openssl.exe',
            'C:\\xampp\\php\\extras\\openssl\\openssl.exe',
            '/usr/bin/openssl',
            '/usr/local/bin/openssl',
        ]);

        if (!$mysqldump || !$openssl) {
            throw new RuntimeException('mysqldump and OpenSSL are required for large database backups.');
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the backup directory.');
        }

        $dumpPath = tempnam(sys_get_temp_dir(), 'bio_dump_');
        $defaultsPath = tempnam(sys_get_temp_dir(), 'bio_mysql_');
        if (!$dumpPath || !$defaultsPath) {
            throw new RuntimeException('Unable to create temporary backup files.');
        }

        // Use Laravel's active, URL-resolved connection values. Reading the raw
        // config array can lose credentials supplied through DB_URL in production.
        $connection = DB::connection()->getConfig();
        $defaults = "[client]\n"
            . 'host=' . $this->quoteMySqlOption((string) ($connection['host'] ?? '127.0.0.1')) . "\n"
            . 'port=' . (int) ($connection['port'] ?? 3306) . "\n"
            . 'user=' . $this->quoteMySqlOption((string) ($connection['username'] ?? '')) . "\n";
        if (!empty($connection['unix_socket'])) {
            $defaults .= 'socket=' . $this->quoteMySqlOption((string) $connection['unix_socket']) . "\n";
        }
        file_put_contents($defaultsPath, $defaults);

        try {
            $this->runProcess([
                $mysqldump,
                "--defaults-extra-file={$defaultsPath}",
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--events',
                '--hex-blob',
                "--result-file={$dumpPath}",
                (string) ($connection['database'] ?? ''),
            ], ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);

            $this->runProcess([
                $openssl,
                'enc', '-aes-256-cbc', '-pbkdf2', '-iter', (string) self::PBKDF2_ITERATIONS,
                '-salt', '-in', $dumpPath, '-out', $outputPath,
                '-pass', 'env:BIO_BACKUP_PASSPHRASE',
            ], ['BIO_BACKUP_PASSPHRASE' => $passphrase]);
        } finally {
            @unlink($dumpPath);
            @unlink($defaultsPath);
        }

        if (!is_file($outputPath) || filesize($outputPath) <= 0) {
            throw new RuntimeException('The encrypted backup file was not created.');
        }

        return ['format' => 'mysql-dump-aes256-v2', 'size_bytes' => filesize($outputPath)];
    }

    public function restoreEncryptedBackupFile(string $backupPath, string $passphrase): array
    {
        if (!is_file($backupPath) || !is_readable($backupPath)) {
            throw new RuntimeException('Unable to read the backup file.');
        }

        $handle = fopen($backupPath, 'rb');
        $signature = $handle ? fread($handle, 8) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }

        // Older exports use the PHP JSON envelope. Keep them restorable.
        if ($signature !== 'Salted__') {
            $content = file_get_contents($backupPath);
            if ($content === false) {
                throw new RuntimeException('Unable to read the backup file.');
            }

            return $this->restoreSnapshot($this->decryptSnapshot($content, $passphrase));
        }

        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('This native SQL backup can only be restored to MySQL or MariaDB.');
        }

        $openssl = $this->findOpenSslBinary();
        $mysql = $this->firstExistingBinary([
            env('MYSQL_PATH'),
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Workbench 8.0 CE\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mariadb.exe',
            '/usr/bin/mysql',
            '/usr/bin/mariadb',
            '/usr/local/bin/mysql',
            '/usr/local/bin/mariadb',
        ]);

        if (!$openssl || !$mysql) {
            throw new RuntimeException('The MySQL client and OpenSSL are required to restore this backup.');
        }

        $sqlPath = tempnam(sys_get_temp_dir(), 'bio_restore_');
        $defaultsPath = tempnam(sys_get_temp_dir(), 'bio_mysql_');
        if (!$sqlPath || !$defaultsPath) {
            throw new RuntimeException('Unable to create temporary restore files.');
        }

        $connection = DB::connection()->getConfig();
        $defaults = "[client]\n"
            . 'host=' . $this->quoteMySqlOption((string) ($connection['host'] ?? '127.0.0.1')) . "\n"
            . 'port=' . (int) ($connection['port'] ?? 3306) . "\n"
            . 'user=' . $this->quoteMySqlOption((string) ($connection['username'] ?? '')) . "\n";
        if (!empty($connection['unix_socket'])) {
            $defaults .= 'socket=' . $this->quoteMySqlOption((string) $connection['unix_socket']) . "\n";
        }
        file_put_contents($defaultsPath, $defaults);

        try {
            $this->runProcess([
                $openssl,
                'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-iter', (string) self::PBKDF2_ITERATIONS,
                '-in', $backupPath, '-out', $sqlPath,
                '-pass', 'env:BIO_BACKUP_PASSPHRASE',
            ], ['BIO_BACKUP_PASSPHRASE' => $passphrase]);

            $this->runProcess([
                $mysql,
                "--defaults-extra-file={$defaultsPath}",
                (string) ($connection['database'] ?? ''),
            ], ['MYSQL_PWD' => (string) ($connection['password'] ?? '')], $sqlPath);
        } finally {
            @unlink($sqlPath);
            @unlink($defaultsPath);
        }

        return [
            'restored_tables' => null,
            'missing_tables' => [],
            'format' => 'mysql-dump-aes256-v2',
        ];
    }

    private function findOpenSslBinary(): ?string
    {
        return $this->firstExistingBinary([
            env('OPENSSL_PATH'),
            'C:\\Program Files\\Git\\mingw64\\bin\\openssl.exe',
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
            'C:\\xampp\\apache\\bin\\openssl.exe',
            'C:\\xampp\\php\\extras\\openssl\\openssl.exe',
            '/usr/bin/openssl',
            '/usr/local/bin/openssl',
        ]);
    }

    private function firstExistingBinary(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function quoteMySqlOption(string $value): string
    {
        $value = str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '', ''], $value);
        return '"' . $value . '"';
    }

    private function runProcess(array $command, array $environment = [], ?string $inputPath = null): void
    {
        $pipes = [];
        $inheritedEnvironment = getenv();
        $processEnvironment = $environment === []
            ? null
            : array_merge(is_array($inheritedEnvironment) ? $inheritedEnvironment : [], $environment);
        $descriptorSpec = [
            0 => $inputPath === null ? ['pipe', 'r'] : ['file', $inputPath, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptorSpec, $pipes, null, $processEnvironment);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the database backup process.');
        }
        if ($inputPath === null && isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr ?: $stdout) ?: 'Database backup process failed.');
        }
    }

    private function prepareLargeBackupRuntime(): void
    {
        @set_time_limit(0);

        $currentLimit = ini_get('memory_limit');
        if ($currentLimit === '-1') {
            return;
        }

        $currentBytes = $this->phpMemoryValueToBytes((string) $currentLimit);
        $requiredBytes = $this->phpMemoryValueToBytes(self::BACKUP_MEMORY_LIMIT);
        if ($currentBytes > 0 && $currentBytes < $requiredBytes) {
            @ini_set('memory_limit', self::BACKUP_MEMORY_LIMIT);
        }
    }

    private function phpMemoryValueToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function buildSnapshot(): array
    {
        $this->prepareLargeBackupRuntime();
        $driver = DB::getDriverName();
        $tables = array_values(array_filter(Schema::getTableListing(), function (string $table): bool {
            return !str_starts_with($table, 'sqlite_');
        }));

        $data = [];

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(function ($row) {
                return $this->normalizeRowForSnapshot((array) $row);
            })->all();

            $data[$table] = $rows;
        }

        return [
            'meta' => [
                'format' => 'biometric-system-db-backup',
                'format_version' => 1,
                'created_at_utc' => now()->utc()->toIso8601String(),
                'source_driver' => $driver,
            ],
            'tables' => $data,
        ];
    }

    public function encryptSnapshot(array $snapshot, string $passphrase): string
    {
        $this->prepareLargeBackupRuntime();
        if (trim($passphrase) === '') {
            throw new RuntimeException('Encryption passphrase is required.');
        }

        $plainJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $salt = random_bytes(16);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $key = hash_pbkdf2('sha256', $passphrase, $salt, self::PBKDF2_ITERATIONS, 32, true);

        $cipherRaw = openssl_encrypt($plainJson, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($cipherRaw === false) {
            throw new RuntimeException('Failed to encrypt backup.');
        }

        $envelope = [
            'version' => self::PAYLOAD_VERSION,
            'cipher' => self::CIPHER,
            'kdf' => 'pbkdf2-sha256',
            'iterations' => self::PBKDF2_ITERATIONS,
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'payload' => base64_encode($cipherRaw),
        ];

        return base64_encode(json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function decryptSnapshot(string $encodedBackup, string $passphrase): array
    {
        $this->prepareLargeBackupRuntime();
        if (trim($passphrase) === '') {
            throw new RuntimeException('Decryption passphrase is required.');
        }

        $decodedEnvelope = base64_decode(trim($encodedBackup), true);
        if ($decodedEnvelope === false) {
            throw new RuntimeException('Invalid backup file format.');
        }

        $envelope = json_decode($decodedEnvelope, true);
        if (!is_array($envelope)) {
            throw new RuntimeException('Backup payload is not valid JSON.');
        }

        $required = ['cipher', 'iterations', 'salt', 'iv', 'payload'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $envelope)) {
                throw new RuntimeException('Backup payload is missing required fields.');
            }
        }

        if (($envelope['cipher'] ?? null) !== self::CIPHER) {
            throw new RuntimeException('Unsupported backup cipher.');
        }

        $salt = base64_decode((string) $envelope['salt'], true);
        $iv = base64_decode((string) $envelope['iv'], true);
        $cipherRaw = base64_decode((string) $envelope['payload'], true);
        $iterations = (int) $envelope['iterations'];

        if ($salt === false || $iv === false || $cipherRaw === false || $iterations <= 0) {
            throw new RuntimeException('Backup payload contains invalid binary data.');
        }

        $key = hash_pbkdf2('sha256', $passphrase, $salt, $iterations, 32, true);
        $plainJson = openssl_decrypt($cipherRaw, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($plainJson === false) {
            throw new RuntimeException('Unable to decrypt backup. Check the passphrase and file.');
        }

        $snapshot = json_decode($plainJson, true);
        if (!is_array($snapshot) || !isset($snapshot['tables']) || !is_array($snapshot['tables'])) {
            throw new RuntimeException('Decrypted backup content is invalid.');
        }

        return $snapshot;
    }

    public function restoreSnapshot(array $snapshot): array
    {
        $driver = DB::getDriverName();
        $tables = array_values(array_filter(Schema::getTableListing(), function (string $table): bool {
            return !str_starts_with($table, 'sqlite_');
        }));

        $existingTableLookup = array_flip($tables);
        $tablesFromBackup = array_keys($snapshot['tables']);
        $missingTables = array_values(array_filter($tablesFromBackup, function (string $table) use ($existingTableLookup): bool {
            return !isset($existingTableLookup[$table]);
        }));

        DB::transaction(function () use ($driver, $tables, $snapshot) {
            $this->disableForeignKeys($driver);

            try {
                foreach (array_reverse($tables) as $table) {
                    if (!array_key_exists($table, $snapshot['tables'])) {
                        continue;
                    }

                    // MySQL TRUNCATE performs implicit commits, which breaks outer transactions.
                    if ($driver === 'sqlite' || $driver === 'mysql') {
                        DB::table($table)->delete();
                        continue;
                    }

                    DB::table($table)->truncate();
                }

                foreach ($tables as $table) {
                    if (!array_key_exists($table, $snapshot['tables'])) {
                        continue;
                    }

                    $rows = $snapshot['tables'][$table];
                    if (!is_array($rows) || $rows === []) {
                        continue;
                    }

                    $rows = array_values(array_map(function ($row) {
                        return is_array($row) ? $this->decodeRowFromSnapshot($row) : [];
                    }, $rows));

                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            } finally {
                $this->enableForeignKeys($driver);
            }
        });

        return [
            'restored_tables' => count(array_intersect($tablesFromBackup, $tables)),
            'missing_tables' => $missingTables,
        ];
    }

    private function disableForeignKeys(string $driver): void
    {
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("SET session_replication_role = 'replica'");
        }
    }

    private function enableForeignKeys(string $driver): void
    {
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("SET session_replication_role = 'origin'");
        }
    }

    private function normalizeRowForSnapshot(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $this->encodeValueForSnapshot($value);
        }

        return $row;
    }

    private function decodeRowFromSnapshot(array $row): array
    {
        foreach ($row as $key => $value) {
            $row[$key] = $this->decodeValueFromSnapshot($value);
        }

        return $row;
    }

    private function encodeValueForSnapshot(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                $value[$key] = $this->encodeValueForSnapshot($nestedValue);
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($value === '' || preg_match('//u', $value) === 1) {
            return $value;
        }

        return [
            '__backup_type' => 'base64-binary',
            'value' => base64_encode($value),
        ];
    }

    private function decodeValueFromSnapshot(mixed $value): mixed
    {
        if (is_array($value)) {
            if (($value['__backup_type'] ?? null) === 'base64-binary' && isset($value['value']) && is_string($value['value'])) {
                $decoded = base64_decode($value['value'], true);

                return $decoded === false ? null : $decoded;
            }

            foreach ($value as $key => $nestedValue) {
                $value[$key] = $this->decodeValueFromSnapshot($nestedValue);
            }

            return $value;
        }

        return $value;
    }
}
