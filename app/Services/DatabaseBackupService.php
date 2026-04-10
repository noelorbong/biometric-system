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

    public function buildSnapshot(): array
    {
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
