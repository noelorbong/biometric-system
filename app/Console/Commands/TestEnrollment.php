<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Diagnostic command: probe every known CMD_STARTENROLL payload variant
 * against a real device so we can see which one the firmware accepts.
 *
 * Usage:
 *   php artisan test:enrollment <machine_id> <user_id> <finger_id>
 *
 * Example:
 *   php artisan test:enrollment 1 3 0
 */
class TestEnrollment extends Command
{
    protected $signature = 'test:enrollment
                            {machine_id : ID of the Machine record}
                            {user_id    : ID of the User record}
                            {finger_id  : Finger slot 0-9}';

    protected $description = 'Probe CMD_STARTENROLL payload variants against a ZKTeco device (diagnostic)';

    // Command codes (mirrors ZKTecoService constants)
    private const TCP_HEADER        = "\x50\x50\x82\x7D";
    private const CMD_CONNECT       = 1000;
    private const CMD_EXIT          = 1001;
    private const CMD_ENABLEDEVICE  = 1002;
    private const CMD_DISABLEDEVICE = 1003;
    private const CMD_ACK_OK        = 2000;
    private const CMD_ACK_ERROR     = 2001;
    private const CMD_ACK_UNAUTH    = 2005;
    private const CMD_AUTH          = 1102;
    private const CMD_OPTIONS_WRQ   = 12;
    private const CMD_REG_EVENT     = 500;
    private const CMD_STARTENROLL   = 61;
    private const CMD_USER_WRQ      = 8;
    private const CMD_DELETE_USER   = 18;

    private $socket = null;
    private int $sessionId = 0;
    private int $replyId   = 0;

    public function handle(): int
    {
        $machine = Machine::find($this->argument('machine_id'));
        $user    = User::with(['profile', 'biometricInfo'])->find($this->argument('user_id'));
        $finger  = (int) $this->argument('finger_id');

        if (!$machine || !$user) {
            $this->error('Machine or user not found.');
            return 1;
        }

        $biometric   = $user->biometricInfo;
        $displayName = trim(implode(' ', array_filter([
            $user->profile?->first_name,
            $user->profile?->last_name,
        ]))) ?: $user->name;

        $badgeInt  = (int) ($biometric?->Badgenumber ?: $user->id);
        $badgeStr  = (string) ($biometric?->Badgenumber ?: $user->id);
        $uid       = (int) $user->id;

        $this->info("═══════════════════════════════════════");
        $this->info("  ZKTeco Enrollment Diagnostic");
        $this->info("═══════════════════════════════════════");
        $this->line("  Machine : {$machine->MachineAlias} ({$machine->IP}:{$machine->Port})");
        $this->line("  User    : {$displayName} (user.id={$uid}, badge={$badgeStr})");
        $this->line("  Finger  : {$finger}");
        $this->info("═══════════════════════════════════════");

        $password = blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword;

        // ── Payload variants to try ────────────────────────────────────────
        $variants = [
            'pyzk tcp 24s+fid+flag'   => pack('a24CC', substr(str_pad($badgeStr, 24, "\x00"), 0, 24), $finger, 1),
            'uid=user.id  VV  (4B+4B)'  => pack('VV', $uid,      $finger),
            'uid=badge    VV  (4B+4B)'  => pack('VV', $badgeInt,  $finger),
            'uid=user.id  vC  (2B+1B)'  => pack('vC', $uid,      $finger),
            'uid=badge    vC  (2B+1B)'  => pack('vC', $badgeInt,  $finger),
        ];

        foreach ($variants as $label => $payload) {
            $this->newLine();
            $this->line("─────────────────────────────────────");
            $this->line("  Variant: <comment>{$label}</comment>");
            $hex = strtoupper(bin2hex($payload));
            $this->line("  Payload (hex): {$hex}");

            try {
                $this->openSocket($machine->IP, $machine->Port ?? 4370, $password);
                $this->cmd(self::CMD_OPTIONS_WRQ, "SDKBuild=1\x00");

                // Push the user so they exist on device
                $this->pushUser($uid, $uid, $displayName,
                    $biometric?->PASSWORD ?? '',
                    $biometric?->CardNo ?? '',
                    (int) ($biometric?->privilege ?? 0),
                    $badgeStr);

                $this->cmd(self::CMD_ENABLEDEVICE);
                $this->cmd(self::CMD_REG_EVENT, pack('V', 0xFFFF));

                $reply = $this->cmd(self::CMD_STARTENROLL, $payload);
                $cmdCode = $reply['cmd'];
                $cmdName = match ($cmdCode) {
                    self::CMD_ACK_OK    => 'ACK_OK ✓',
                    self::CMD_ACK_ERROR => 'ACK_ERROR ✗',
                    self::CMD_ACK_UNAUTH => 'ACK_UNAUTH',
                    default             => "CMD_{$cmdCode}",
                };

                if ($cmdCode === self::CMD_ACK_OK) {
                    $this->info("  Result  : {$cmdName} — DEVICE SHOULD NOW SHOW ENROLLMENT PROMPT");
                } else {
                    $this->warn("  Result  : {$cmdName}");
                }

                if ($reply['data'] !== '') {
                    $this->line("  Data    : " . strtoupper(bin2hex($reply['data'])));
                }

                $this->closeSocket();

            } catch (\Throwable $e) {
                $this->error("  Error   : " . $e->getMessage());
                $this->safeClose();
            }
        }

        $this->newLine();
        $this->info("═══════════════════════════════════════");
        $this->line("Done. Check the device screen — whichever variant triggered the");
        $this->line("enrollment prompt on screen is the correct one to use.");
        $this->info("═══════════════════════════════════════");

        return 0;
    }

    // ─── Low-level helpers (self-contained) ─────────────────────────────────

    private function pushUser(int $uid, int $displayUid, string $name, string $password, $card, int $privilege, string $userId): void
    {
        $cardNumber = is_numeric($card) ? (int) $card : 0;
        $payload = pack(
            'vCa8a24a4xa7xa24',
            $uid,
            max(0, min(14, $privilege)),
            substr($password, 0, 8),
            substr($name, 0, 24),
            pack('V', $cardNumber),
            '1',
            substr($userId, 0, 24)
        );

        try {
            $this->cmd(self::CMD_USER_WRQ, $payload);
        } catch (\Throwable) {
            $this->cmd(self::CMD_DELETE_USER, pack('v', $uid));
            $this->cmd(self::CMD_USER_WRQ, $payload);
        }
    }

    private function openSocket(string $ip, int $port, string $password): void
    {
        $socket = @fsockopen('tcp://' . $ip, $port, $errno, $errstr, 10);
        if ($socket === false) {
            throw new \RuntimeException("Cannot connect to {$ip}:{$port} — {$errstr}");
        }
        stream_set_timeout($socket, 10);
        $this->socket    = $socket;
        $this->sessionId = 0;
        $this->replyId   = 0;

        $reply = $this->cmd(self::CMD_CONNECT);

        if ($reply['cmd'] === self::CMD_ACK_UNAUTH) {
            $this->sessionId = $reply['session_id'];
            $authReply = $this->cmd(self::CMD_AUTH, $this->makeCommKey($password));
            if ($authReply['cmd'] !== self::CMD_ACK_OK) {
                throw new \RuntimeException('Auth rejected by device.');
            }
            $reply = $authReply;
        }

        if ($reply['cmd'] !== self::CMD_ACK_OK) {
            throw new \RuntimeException('Connection handshake failed (cmd=' . $reply['cmd'] . ')');
        }
        $this->sessionId = $reply['session_id'];
    }

    private function closeSocket(): void
    {
        if ($this->socket) {
            try { $this->cmd(self::CMD_EXIT); } catch (\Throwable) {}
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function safeClose(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function cmd(int $cmd, string $data = '', ?int $replyId = null): array
    {
        if ($replyId !== null) {
            $this->replyId = $replyId & 0xFFFF;
        } elseif ($cmd === self::CMD_CONNECT) {
            $this->replyId = 0;
        } else {
            $this->replyId = ($this->replyId + 1) & 0xFFFF;
        }

        $zkPacket = pack('vvvv', $cmd, 0, $this->sessionId, $this->replyId) . $data;
        $checksum = $this->checksum16($zkPacket);
        $zkPacket = pack('vvvv', $cmd, $checksum, $this->sessionId, $this->replyId) . $data;
        $frame    = self::TCP_HEADER . pack('V', strlen($zkPacket)) . $zkPacket;

        if (@fwrite($this->socket, $frame) === false) {
            throw new \RuntimeException("Failed to write command {$cmd}");
        }

        // Read TCP frame header (8 bytes)
        $tcpHeader = $this->recvExact(8);
        if ($tcpHeader === null) {
            throw new \RuntimeException("No response for command {$cmd}");
        }
        if (substr($tcpHeader, 0, 4) !== self::TCP_HEADER) {
            throw new \RuntimeException("Bad TCP magic for command {$cmd}");
        }
        $zkLen = unpack('V', substr($tcpHeader, 4, 4))[1];
        if ($zkLen < 8) {
            throw new \RuntimeException("ZK packet too short for command {$cmd}");
        }
        $zkPacket = $this->recvExact($zkLen);
        if ($zkPacket === null) {
            throw new \RuntimeException("Incomplete ZK packet for command {$cmd}");
        }
        $fields = unpack('vcmd/vchecksum/vsession_id/vreply_id', substr($zkPacket, 0, 8));
        return [
            'cmd'        => $fields['cmd'],
            'session_id' => $fields['session_id'],
            'reply_id'   => $fields['reply_id'],
            'data'       => substr($zkPacket, 8),
        ];
    }

    private function recvExact(int $length): ?string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = @fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') return null;
            $data .= $chunk;
        }
        return $data;
    }

    private function checksum16(string $data): int
    {
        $sum = 0;
        $len = strlen($data);
        for ($i = 0; $i + 1 < $len; $i += 2) {
            $sum += unpack('v', $data[$i] . $data[$i + 1])[1];
        }
        if ($len % 2 !== 0) $sum += ord($data[$len - 1]);
        while ($sum >> 16) $sum = ($sum & 0xFFFF) + ($sum >> 16);
        return (~$sum) & 0xFFFF;
    }

    private function makeCommKey(string $password, int $ticks = 50): string
    {
        $key       = (int) $password;
        $sessionId = $this->sessionId;
        $scrambled = 0;

        for ($bit = 0; $bit < 32; $bit++) {
            $scrambled = ($scrambled << 1) | (($key & (1 << $bit)) ? 1 : 0);
        }

        $scrambled += $sessionId;

        $bytes = array_values(unpack('C4', pack('V', $scrambled)));
        $bytes = [
            $bytes[0] ^ ord('Z'),
            $bytes[1] ^ ord('K'),
            $bytes[2] ^ ord('S'),
            $bytes[3] ^ ord('O'),
        ];

        $halves = array_values(unpack('v2', pack('C4', ...$bytes)));
        $bytes  = array_values(unpack('C4', pack('v2', $halves[1], $halves[0])));
        $mask   = $ticks & 0xFF;

        return pack(
            'C4',
            $bytes[0] ^ $mask,
            $bytes[1] ^ $mask,
            $mask,
            $bytes[3] ^ $mask
        );
    }
}
