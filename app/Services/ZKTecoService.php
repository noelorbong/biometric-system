<?php

namespace App\Services;

use RuntimeException;

/**
 * ZKTeco / Granding FA1-PRO TCP protocol service.
 *
 * Supported operations:
 *  - connect / disconnect
 *  - getDeviceInfo  (serial number, firmware version, device name, etc.)
 *  - getAttendanceLogs
 */
class ZKTecoService
{
    private const TCP_HEADER         = "\x50\x50\x82\x7D";

    // ─── Command codes ────────────────────────────────────────────────────────
    private const CMD_CONNECT        = 1000;
    private const CMD_EXIT           = 1001;
    private const CMD_ENABLEDEVICE   = 1002;
    private const CMD_DISABLEDEVICE  = 1003;
    private const CMD_ACK_OK         = 2000;
    private const CMD_ACK_ERROR      = 2001;
    private const CMD_ACK_UNAUTH     = 2005;
    private const CMD_GET_VERSION    = 1100;
    private const CMD_AUTH           = 1102;
    private const CMD_PREPARE_DATA   = 1500;
    private const CMD_DATA           = 1501;
    private const CMD_FREE_DATA      = 1502;
    private const CMD_DATA_WRRQ      = 1503;
    private const CMD_DATA_RDY       = 1504;
    private const CMD_USER_WRQ       = 8;
    private const CMD_USERTEMP_RRQ   = 9;
    private const CMD_ATTLOG_RRQ     = 13;
    private const CMD_CLEAR_ATTLOG   = 15;
    private const CMD_DELETE_USER    = 18;
    private const CMD_GET_USERTEMP   = 88;
    private const CMD_SAVE_USERTEMPS = 110;
    private const CMD_STARTENROLL    = 61;
    private const CMD_CANCELCAPTURE  = 62;
    private const CMD_REG_EVENT      = 500;
    private const CMD_OPTIONS_RRQ    = 11;
    private const CMD_OPTIONS_WRQ    = 12;

    /** @var resource|null */
    private $socket = null;

    private int $sessionId = 0;
    private int $replyId   = 0;

    public function __construct(
        private readonly string $ip,
        private readonly int    $port     = 4370,
        private readonly int    $timeout  = 10,
        private readonly string $password = ''
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Open a TCP connection and perform the ZKTeco handshake.
     *
     * @throws RuntimeException on failure
     */
    public function connect(): void
    {
        $socket = @fsockopen('tcp://' . $this->ip, $this->port, $errno, $errstr, $this->timeout);

        if ($socket === false) {
            throw new RuntimeException(
                "Cannot connect to device at {$this->ip}:{$this->port} — {$errstr} (errno {$errno})"
            );
        }

        stream_set_timeout($socket, $this->timeout);

        $this->socket    = $socket;
        $this->sessionId = 0;
        $this->replyId   = 0;

        $reply = $this->sendCommand(self::CMD_CONNECT);

        if ($reply['cmd'] === self::CMD_ACK_UNAUTH) {
            $this->sessionId = $reply['session_id'];

            $authReply = $this->sendCommand(self::CMD_AUTH, $this->makeCommKey());

            if ($authReply['cmd'] !== self::CMD_ACK_OK) {
                fclose($this->socket);
                $this->socket = null;
                throw new RuntimeException('Communication key rejected by device.');
            }

            $reply = $authReply;
        }

        if ($reply['cmd'] !== self::CMD_ACK_OK) {
            fclose($this->socket);
            $this->socket = null;
            throw new RuntimeException('Device rejected connection handshake (expected CMD_ACK_OK)');
        }

        $this->sessionId = $reply['session_id'];

        // Recommended by the TCP protocol documentation after connect.
        try {
            $this->sendCommand(self::CMD_OPTIONS_WRQ, "SDKBuild=1\x00");
        } catch (\Throwable) {
            // Some firmwares ignore this setting; keep the session alive anyway.
        }
    }

    /**
     * Gracefully close the connection.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            try {
                $this->sendCommand(self::CMD_EXIT);
            } catch (\Throwable) {
                // best-effort
            }
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Close the TCP socket without sending CMD_EXIT.
     * Useful when a command should keep running on-device after request ends.
     */
    public function closeTransport(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Read key device fields and return them as an associative array.
     *
     * Expected keys: SerialNumber, FirmVer, DeviceName, ProduceKind, Manufacturer
     */
    public function getDeviceInfo(): array
    {
        $fields = [
            '~SerialNumber' => 'SerialNumber',
            'DeviceName' => 'DeviceName',
            '~Platform' => 'Platform',
            'WorkCode' => 'WorkCode',
        ];
        $info   = [];

        foreach ($fields as $field => $key) {
            try {
                $reply = $this->sendCommand(self::CMD_OPTIONS_RRQ, $field . "\x00");

                if ($reply['cmd'] === self::CMD_ACK_OK && isset($reply['data'])) {
                    $info[$key] = $this->parseOptionValue($reply['data']);
                }
            } catch (\Throwable) {
                // field not supported by this device — skip
            }
        }

        try {
            $reply = $this->sendCommand(self::CMD_GET_VERSION);
            if ($reply['cmd'] === self::CMD_ACK_OK && isset($reply['data'])) {
                $info['FirmVer'] = rtrim($reply['data'], "\x00\r\n ");
            }
        } catch (\Throwable) {
            // optional field
        }

        return $info;
    }

    /**
     * Download all attendance log records from the device.
     *
     * Each record is an array:
     *   [pin, check_time, check_type ('I'|'O'), verify_code]
     *
     * @return array<int, array{pin: string, check_time: string, check_type: string, verify_code: int}>
     */
    public function getAttendanceLogs(): array
    {
        $this->sendCommand(self::CMD_DISABLEDEVICE);

        // First try the standalone/TCP attendance payload used by FA/iface models.
        $raw = $this->downloadAttendanceLogsStandalone();
        $logs = $this->parseAttendanceLogs($raw);

        // Older fingerprint-only models often require buffered CMD_ATTLOG_RRQ.
        if ($logs === []) {
            $raw = $this->readWithBuffer(self::CMD_ATTLOG_RRQ);
            $logs = $this->parseAttendanceLogs($raw);
        }

        $this->sendCommand(self::CMD_ENABLEDEVICE);

        return $logs;
    }

    /**
     * Download user records stored on the device.
     *
     * @return array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}>
     */
    public function getUsers(): array
    {
        $this->sendCommand(self::CMD_DISABLEDEVICE);

        try {
            $raw = $this->readWithBuffer(self::CMD_USERTEMP_RRQ, 5, 0);

            if ($raw !== '') {
                return $this->parseUserRecords($raw);
            }

            $payloads = [
                hex2bin('0901000000000000000000'),
                pack('cvVV', 1, 0x05, 0, 0),
            ];

            $raw = '';

            foreach ($payloads as $payload) {
                $reply = $this->sendCommand(self::CMD_DATA_WRRQ, $payload);
                $raw = match ($reply['cmd']) {
                    self::CMD_ACK_OK       => $this->receivePreparedData($reply['reply_id']),
                    self::CMD_DATA         => $reply['data'] ?? '',
                    self::CMD_PREPARE_DATA => $this->receiveChunkedData(
                        $this->unpackUInt32LE($reply['data'] ?? ''),
                        $reply['reply_id']
                    ),
                    default                => '',
                };

                if ($raw !== '') {
                    break;
                }
            }

            return $this->parseUserRecords($raw);
        } finally {
            $this->sendCommand(self::CMD_ENABLEDEVICE);
        }
    }

    /**
     * Disable the device UI so records are not missed during bulk operations.
     */
    public function disableDevice(): void
    {
        $this->sendCommand(self::CMD_DISABLEDEVICE);
    }

    /**
     * Re-enable the device UI after a bulk operation.
     */
    public function enableDevice(): void
    {
        $this->sendCommand(self::CMD_ENABLEDEVICE);
    }

    /**
     * Push one user record to the device.
     *
     * @param array{
     *   uid: int,
     *   badgenumber: string,
     *   name: string,
     *   password?: string,
     *   card?: string,
     *   privilege?: int,
     *   verify_mode?: int,
     * } $user
     *
     * @throws RuntimeException on failure
     */
    public function setUserInfo(array $user): void
    {
        // ZKTeco 72-byte user record layout used by many TFT devices:
        // uid(2) + privilege(1) + password(8) + name(24) + card(4) +
        // reserved(1) + group(7) + reserved(1) + user_id(24)
        $uid = (int) ($user['uid'] ?? 0);
        $privilege = max(0, min(14, (int) ($user['privilege'] ?? 0)));
        $password = substr((string) ($user['password'] ?? ''), 0, 8);
        $name = substr((string) ($user['name'] ?? ''), 0, 24);
        $cardNumber = is_numeric($user['card'] ?? null) ? (int) $user['card'] : 0;
        $card = pack('V', $cardNumber);
        $groupId = substr((string) ($user['group_id'] ?? '1'), 0, 7);
        $userId = substr((string) ($user['badgenumber'] ?? $user['user_id'] ?? $uid), 0, 24);

        $payload = pack(
            'vCa8a24a4xa7xa24',
            $uid,
            $privilege,
            $password,
            $name,
            $card,
            $groupId,
            $userId
        );

        $reply = $this->sendCommand(self::CMD_USER_WRQ, $payload);

        if ($reply['cmd'] !== self::CMD_ACK_OK) {
            throw new RuntimeException(
                "Device rejected user write for UID {$uid} (cmd={$reply['cmd']})"
            );
        }
    }

    /**
     * Trigger on-device fingerprint enrollment for a specific user and finger slot.
     *
     * After receiving ACK_OK the device enters its enrollment UI autonomously.
     * No live TCP connection is required for the user to complete the scan.
     *
     * Finger IDs follow the ZKTeco convention:
     *   0 = Left Pinky  1 = Left Ring   2 = Left Middle  3 = Left Index  4 = Left Thumb
     *   5 = Right Thumb 6 = Right Index 7 = Right Middle 8 = Right Ring  9 = Right Pinky
     *
    * @param int    $uid      Internal device UID (same value used in setUserInfo)
    * @param int    $fingerId Finger slot 0–9
    * @param string $userId   Badge-number / PIN string (used by TCP StartEnroll variants)
     *
     * @throws RuntimeException if the device rejects the request
     */
    public function startEnrollment(int $uid, int $fingerId, string $userId = ''): void
    {
        $fingerId = max(0, min(9, $fingerId));
        $userId = (string) ($userId !== '' ? $userId : $uid);

        // Different firmwares accept different StartEnroll payloads.
        // Try the TCP shape used by pyzk first, then documented/fallback variants.
        $tcpUser = substr(str_pad($userId, 24, "\x00"), 0, 24);
        $numericUser = ctype_digit($userId) ? (int) $userId : $uid;

        $variants = [
            // pyzk TCP variant: <24sbb>  user_id(24) + finger(1) + enroll_flag(1)
            pack('a24CC', $tcpUser, $fingerId, 1),

            // COM docs style: StartEnroll(LONG UserID, LONG FingerID)
            pack('VV', $numericUser, $fingerId),

            // Legacy compact variant observed in some firmwares
            pack('vC', $uid, $fingerId),

            // Older hybrid variant: uid(2) + fid(1) + user_id(24)
            pack('vC', $uid, $fingerId) . $tcpUser,
        ];

        $lastCmd = null;

        foreach ($variants as $payload) {
            $reply = $this->sendCommand(self::CMD_STARTENROLL, $payload);
            $lastCmd = $reply['cmd'] ?? null;

            if ($lastCmd === self::CMD_ACK_OK) {
                return;
            }
        }

        throw new RuntimeException(
            "Device rejected enrollment for UID {$uid} finger {$fingerId} (last_cmd={$lastCmd})"
        );
    }

    /**
     * Trigger on-device face enrollment for a specific user.
     *
     * Different ZKTeco firmwares map face enrollment to different backup numbers,
     * so this method tries commonly used values until one is accepted.
     *
     * @throws RuntimeException if the device rejects all known face backup codes.
     */
    public function startFaceEnrollment(int $uid, string $userId = ''): void
    {
        $userId = (string) ($userId !== '' ? $userId : $uid);

        // Common backup numbers observed for face capture across firmware variants.
        $faceBackupCandidates = [111, 50, 12, 15];

        $tcpUser = substr(str_pad($userId, 24, "\x00"), 0, 24);
        $numericUser = ctype_digit($userId) ? (int) $userId : $uid;
        $lastCmd = null;

        foreach ($faceBackupCandidates as $backupNumber) {
            $variants = [
                pack('a24CC', $tcpUser, $backupNumber, 1),
                pack('VV', $numericUser, $backupNumber),
                pack('vC', $uid, $backupNumber),
                pack('vC', $uid, $backupNumber) . $tcpUser,
            ];

            foreach ($variants as $payload) {
                $reply = $this->sendCommand(self::CMD_STARTENROLL, $payload);
                $lastCmd = $reply['cmd'] ?? null;

                if ($lastCmd === self::CMD_ACK_OK) {
                    return;
                }
            }
        }

        throw new RuntimeException(
            "Device rejected face enrollment for UID {$uid} (last_cmd={$lastCmd})"
        );
    }

    /**
     * Cancel any ongoing capture/enrollment session on the device.
     */
    public function cancelCapture(): void
    {
        try {
            $this->sendCommand(self::CMD_CANCELCAPTURE);
        } catch (\Throwable) {
            // best-effort
        }
    }

    /**
     * Register real-time event flags for the current session.
     */
    public function registerEvents(int $flags): void
    {
        $this->sendCommand(self::CMD_REG_EVENT, pack('V', $flags));
    }

    /**
     * Delete a user from the device by UID.
     */
    public function deleteUserInfo(int $uid): void
    {
        $this->sendCommand(self::CMD_DELETE_USER, pack('v', $uid));
    }

    /**
     * Clear attendance logs stored on the device.
     *
     * @throws RuntimeException when the device rejects the request.
     */
    public function clearAttendanceLogs(): void
    {
        $reply = $this->sendCommand(self::CMD_CLEAR_ATTLOG);

        if ($reply['cmd'] !== self::CMD_ACK_OK) {
            throw new RuntimeException(
                "Device rejected attendance clear request (cmd={$reply['cmd']})"
            );
        }
    }

    /**
     * Download a single fingerprint template from the device.
     *
     * Returns raw template bytes or null when not found.
     */
    public function getUserTemplate(int $uid, int $fingerId): ?string
    {
        $fingerId = max(0, min(9, $fingerId));

        return $this->getUserTemplateByBackupNumber($uid, $fingerId);
    }

    /**
     * Download a single biometric template by backup number.
     *
     * Fingerprint slots are usually 0-9 while face templates can use other IDs
     * depending on firmware (for example 50 or 111).
     */
    public function getUserTemplateByBackupNumber(int $uid, int $backupNumber): ?string
    {
        $backupNumber = max(0, min(255, $backupNumber));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $reply = $this->sendCommand(self::CMD_GET_USERTEMP, pack('vC', $uid, $backupNumber));

            if ($reply['cmd'] === self::CMD_ACK_ERROR) {
                continue;
            }

            $raw = match ($reply['cmd']) {
                self::CMD_ACK_OK       => $this->receivePreparedData($reply['reply_id']),
                self::CMD_DATA         => $reply['data'] ?? '',
                self::CMD_PREPARE_DATA => $this->receiveChunkedData(
                    $this->unpackUInt32LE($reply['data'] ?? ''),
                    $reply['reply_id']
                ),
                default                => '',
            };

            if ($raw === '') {
                continue;
            }

            // pyzk behavior: drop trailing marker byte and common null padding.
            if (strlen($raw) > 0) {
                $raw = substr($raw, 0, -1);
            }
            if (strlen($raw) >= 6 && substr($raw, -6) === "\x00\x00\x00\x00\x00\x00") {
                $raw = substr($raw, 0, -6);
            }

            if ($raw !== '') {
                return $raw;
            }
        }

        return null;
    }

    /**
     * Upload one or more fingerprint templates for an existing user.
     *
     * @param int $uid Internal device UID.
     * @param string $userId Badge/PIN as stored on device.
     * @param array<int, array{finger_id:int, template:string}> $fingerTemplates
     * @param array{name?:string,password?:string,card?:int|string,privilege?:int,group_id?:string} $userMeta
     *
     * @throws RuntimeException when upload is rejected by device.
     */
    public function saveUserTemplates(int $uid, string $userId, array $fingerTemplates, array $userMeta = []): void
    {
        if ($fingerTemplates === []) {
            return;
        }

        $upack = $this->buildHighRateUserRecord(
            uid: $uid,
            userId: $userId,
            name: (string) ($userMeta['name'] ?? ''),
            password: (string) ($userMeta['password'] ?? ''),
            card: $userMeta['card'] ?? 0,
            privilege: (int) ($userMeta['privilege'] ?? 0),
            groupId: (string) ($userMeta['group_id'] ?? '1')
        );

        $fpack = '';
        $table = '';
        $offset = 0;

        foreach ($fingerTemplates as $entry) {
            $fid = max(0, min(9, (int) ($entry['finger_id'] ?? 0)));
            $tpl = (string) ($entry['template'] ?? '');

            if ($tpl === '') {
                continue;
            }

            // Finger.repack_only(): size(2 LE) + template bytes
            $packedTemplate = pack('v', strlen($tpl)) . $tpl;

            // Relation row: STX(0x02) + uid(2 LE) + (finger_id + 0x10) + template offset(4 LE)
            $table .= pack('CvCV', 2, $uid, 0x10 + $fid, $offset);
            $fpack .= $packedTemplate;
            $offset += strlen($packedTemplate);
        }

        if ($table === '' || $fpack === '') {
            return;
        }

        $packet = pack('VVV', strlen($upack), strlen($table), strlen($fpack)) . $upack . $table . $fpack;

        $this->sendWithBuffer($packet);

        // pyzk-compatible finalize command for buffered user+template write.
        $reply = $this->sendCommand(self::CMD_SAVE_USERTEMPS, pack('Vvv', 12, 0, 8));

        if ($reply['cmd'] !== self::CMD_ACK_OK) {
            throw new RuntimeException(
                "Device rejected template save for UID {$uid} (cmd={$reply['cmd']})"
            );
        }
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Receive multiple CMD_DATA packets that together form a large payload,
     * then send CMD_FREE_DATA to signal the device we are done.
     */
    private function receivePreparedData(int $replyId): string
    {
        $reply = $this->sendCommand(self::CMD_DATA_RDY, "\x00\x00\x00\x00", $replyId);

        if ($reply['cmd'] !== self::CMD_PREPARE_DATA) {
            return '';
        }

        $expectedSize = $this->unpackUInt32LE($reply['data'] ?? '');

        return $this->receiveChunkedData($expectedSize, $reply['reply_id']);
    }

    private function receiveChunkedData(int $expectedSize, int $replyId): string
    {
        $raw = '';

        while ($expectedSize > 0 ? strlen($raw) < $expectedSize : true) {
            $reply = $this->readPacket();
            if ($reply === null) {
                break;
            }

            if ($reply['cmd'] === self::CMD_DATA) {
                $raw .= $reply['data'] ?? '';
                continue;
            }

            if ($reply['cmd'] === self::CMD_ACK_OK && $reply['reply_id'] === $replyId) {
                break;
            }

            // For devices that do not declare total size up front,
            // stop once we leave data frames.
            if ($expectedSize <= 0) {
                break;
            }
        }

        $this->sendCommand(self::CMD_FREE_DATA);

        return $raw;
    }

    /**
     * Attendance download style used by newer standalone/TCP devices.
     */
    private function downloadAttendanceLogsStandalone(): string
    {
        $reply = $this->sendCommand(self::CMD_DATA_WRRQ, hex2bin('010d000000000000000000'));

        return match ($reply['cmd']) {
            self::CMD_ACK_OK       => $this->receivePreparedData($reply['reply_id']),
            self::CMD_DATA         => $reply['data'] ?? '',
            self::CMD_PREPARE_DATA => $this->receiveChunkedData(
                $this->unpackUInt32LE($reply['data'] ?? ''),
                $reply['reply_id']
            ),
            default                => '',
        };
    }

    /**
     * Buffered read used by older ZK fingerprint devices.
     */
    private function readWithBuffer(int $command, int $fct = 0, int $ext = 0): string
    {
        $request = pack('cvVV', 1, $command, $fct, $ext);
        $reply = $this->sendCommand(self::CMD_DATA_WRRQ, $request);

        if ($reply['cmd'] === self::CMD_DATA) {
            return $reply['data'] ?? '';
        }

        $size = $this->unpackUInt32LE($reply['data'] ?? '', 1);
        if ($size <= 0) {
            // Some firmwares place the size at offset 0 instead of 1.
            $size = $this->unpackUInt32LE($reply['data'] ?? '', 0);
        }

        if ($size <= 0) {
            if ($reply['cmd'] === self::CMD_PREPARE_DATA) {
                return $this->receiveChunkedData(0, $reply['reply_id']);
            }

            return '';
        }

        $maxChunk = 0xFFC0;
        $data = '';

        for ($offset = 0; $offset < $size; $offset += $maxChunk) {
            $chunkSize = min($maxChunk, $size - $offset);
            $chunkReply = $this->sendCommand(self::CMD_DATA_RDY, pack('VV', $offset, $chunkSize));

            $data .= match ($chunkReply['cmd']) {
                self::CMD_DATA         => $chunkReply['data'] ?? '',
                self::CMD_ACK_OK       => $this->receivePreparedData($chunkReply['reply_id']),
                self::CMD_PREPARE_DATA => $this->receiveChunkedData(
                    $this->unpackUInt32LE($chunkReply['data'] ?? ''),
                    $chunkReply['reply_id']
                ),
                default                => '',
            };
        }

        try {
            $this->sendCommand(self::CMD_FREE_DATA);
        } catch (\Throwable) {
            // best-effort cleanup
        }

        return $data;
    }

    /**
     * Buffered upload helper used by template save flows.
     */
    private function sendWithBuffer(string $buffer): void
    {
        $size = strlen($buffer);
        $maxChunk = 1024;

        // Ensure previous transfer state is cleared.
        $this->sendCommand(self::CMD_FREE_DATA);

        $reply = $this->sendCommand(self::CMD_PREPARE_DATA, pack('V', $size));

        if (!in_array($reply['cmd'], [self::CMD_ACK_OK, self::CMD_PREPARE_DATA], true)) {
            throw new RuntimeException('Device rejected prepare-data for template upload.');
        }

        for ($offset = 0; $offset < $size; $offset += $maxChunk) {
            $chunk = substr($buffer, $offset, $maxChunk);
            $chunkReply = $this->sendCommand(self::CMD_DATA, $chunk);

            if ($chunkReply['cmd'] !== self::CMD_ACK_OK) {
                throw new RuntimeException('Device rejected template data chunk upload.');
            }
        }
    }

    /**
     * Build 73-byte high-rate user record (STX + 72-byte user body).
     */
    private function buildHighRateUserRecord(
        int $uid,
        string $userId,
        string $name = '',
        string $password = '',
        int|string $card = 0,
        int $privilege = 0,
        string $groupId = '1'
    ): string {
        $privilege = max(0, min(14, $privilege));
        $password = substr($password, 0, 8);
        $name = substr($name, 0, 24);
        $groupId = substr($groupId !== '' ? $groupId : '1', 0, 7);
        $userId = substr($userId !== '' ? $userId : (string) $uid, 0, 24);
        $cardNumber = is_numeric($card) ? (int) $card : 0;
        $cardBytes = pack('V', $cardNumber);

        return pack(
            'CvCa8a24a4Ca7xa24',
            2,
            $uid,
            $privilege,
            $password,
            $name,
            $cardBytes,
            1,
            $groupId,
            $userId
        );
    }

    /**
     * Parse raw binary attendance records returned by the device.
     *
     * Modern TCP devices usually prefix the dataset with a 4-byte total-size field,
     * then one of the known attendance record layouts (8, 16 or 40 bytes each).
     *
     * @return array<int, array{uid: int, pin: string, check_time: string, check_type: string, verify_code: int}>
     */
    private function parseAttendanceLogs(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $payload = $raw;

        if (strlen($payload) >= 4) {
            $declaredSize = $this->unpackUInt32LE($payload);

            if ($declaredSize > 0 && $declaredSize <= strlen($payload) - 4) {
                $payload = substr($payload, 4, $declaredSize);
            }
        }

        $recordSize = $this->detectAttendanceRecordSize($payload);

        if ($recordSize === null) {
            return [];
        }

        $records = [];
        $total   = strlen($payload);

        for ($offset = 0; $offset + $recordSize <= $total; $offset += $recordSize) {
            $record = substr($payload, $offset, $recordSize);
            $parsed = match ($recordSize) {
                8 => $this->parseAttendanceRecord8($record),
                16 => $this->parseAttendanceRecord16($record),
                40 => $this->parseAttendanceRecord40($record),
                default => null,
            };

            if ($parsed !== null) {
                $records[] = $parsed;
            }
        }

        return $records;
    }

    private function detectAttendanceRecordSize(string $payload): ?int
    {
        $length = strlen($payload);

        foreach ([40, 16, 8] as $candidate) {
            if ($length >= $candidate && $length % $candidate === 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Parse raw device user records.
     *
     * Supported layouts:
     * - 72-byte TFT user records
     * - 73-byte high-rate user records prefixed with STX (0x02)
     * - payloads prefixed with a 4-byte declared size
     *
     * @return array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}>
     */
    private function parseUserRecords(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $payload = $raw;

        if (strlen($payload) >= 4) {
            $declaredSize = $this->unpackUInt32LE($payload);

            if ($declaredSize > 0 && $declaredSize <= strlen($payload) - 4) {
                $candidate = substr($payload, 4, $declaredSize);

                if ($this->detectUserRecordSize($candidate) !== null) {
                    $payload = $candidate;
                }
            }
        }

        $recordSize = $this->detectUserRecordSize($payload);

        if ($recordSize === null) {
            return [];
        }

        $users = [];
        $total = strlen($payload);

        for ($offset = 0; $offset + $recordSize <= $total; $offset += $recordSize) {
            $record = substr($payload, $offset, $recordSize);

            if ($recordSize === 73) {
                if (ord($record[0]) !== 2) {
                    continue;
                }

                $record = substr($record, 1);
            }

            if ($recordSize === 28) {
                $uid = unpack('v', substr($record, 0, 2))[1] ?? 0;
                $privilege = ord(substr($record, 2, 1) ?: "\x00");
                $password = rtrim(substr($record, 3, 5), "\x00 \r\n\t");
                $name = rtrim(substr($record, 8, 8), "\x00 \r\n\t");
                $card = unpack('V', substr($record, 16, 4))[1] ?? 0;
                $pin = (string) (unpack('V', substr($record, 24, 4))[1] ?? 0);
            } else {
                $uid = unpack('v', substr($record, 0, 2))[1] ?? 0;
                $privilege = ord(substr($record, 2, 1) ?: "\x00");
                $password = rtrim(substr($record, 3, 8), "\x00 \r\n\t");
                $name = rtrim(substr($record, 11, 24), "\x00 \r\n\t");
                $card = unpack('V', substr($record, 35, 4))[1] ?? 0;
                $pin = rtrim(substr($record, 48, 24), "\x00 \r\n\t");
            }

            if ($pin === '' && $uid > 0) {
                $pin = (string) $uid;
            }

            if ($name === '' && $pin !== '') {
                $name = 'NN-' . $pin;
            }

            if ($uid <= 0 && $pin === '' && $name === '') {
                continue;
            }

            $users[] = [
                'uid' => (int) $uid,
                'pin' => (string) $pin,
                'name' => (string) $name,
                'password' => (string) $password,
                'privilege' => (int) $privilege,
                'card' => (int) $card,
            ];
        }

        return $users;
    }

    private function detectUserRecordSize(string $payload): ?int
    {
        $length = strlen($payload);

        foreach ([73, 72, 28] as $candidate) {
            if ($length >= $candidate && $length % $candidate === 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Legacy attendance record layout: uid(2) + verify(1) + time(4) + punch(1)
     */
    private function parseAttendanceRecord8(string $record): ?array
    {
        $fields = unpack('vuid/Cverify/Vtimestamp/Cpunch', $record);

        return $this->buildAttendanceRecord(
            uid: $fields['uid'],
            pin: (string) $fields['uid'],
            encodedTime: $fields['timestamp'],
            verifyCode: $fields['verify'],
            punch: $fields['punch']
        );
    }

    /**
     * Intermediate attendance record layout: user_id(4) + time(4) + verify(1) + punch(1)
     */
    private function parseAttendanceRecord16(string $record): ?array
    {
        $fields = unpack('Vuser_id/Vtimestamp/Cverify/Cpunch/a2reserved/Vworkcode', $record);
        $pin = (string) $fields['user_id'];

        return $this->buildAttendanceRecord(
            uid: (int) $fields['user_id'],
            pin: $pin,
            encodedTime: $fields['timestamp'],
            verifyCode: $fields['verify'],
            punch: $fields['punch']
        );
    }

    /**
     * Common TCP attendance record layout: uid(2) + user_id(24) + verify(1) + time(4) + punch(1)
     */
    private function parseAttendanceRecord40(string $record): ?array
    {
        $fields = unpack('vuid/a24user_id/Cverify/Vtimestamp/Cpunch/a8reserved', $record);
        $pin = rtrim($fields['user_id'], "\x00");

        return $this->buildAttendanceRecord(
            uid: $fields['uid'],
            pin: $pin !== '' ? $pin : (string) $fields['uid'],
            encodedTime: $fields['timestamp'],
            verifyCode: $fields['verify'],
            punch: $fields['punch']
        );
    }

    private function buildAttendanceRecord(int $uid, string $pin, int $encodedTime, int $verifyCode, int $punch): ?array
    {
        $checkTime = $this->decodeAttendanceTime($encodedTime);

        if ($checkTime === null) {
            return null;
        }

        return [
            'uid' => $uid,
            'pin' => trim($pin),
            'check_time' => $checkTime,
            'check_type' => $this->mapPunchToCheckType($punch),
            'verify_code' => $verifyCode,
        ];
    }

    private function decodeAttendanceTime(int $encodedTime): ?string
    {
        $second = $encodedTime % 60;
        $encodedTime = intdiv($encodedTime, 60);

        $minute = $encodedTime % 60;
        $encodedTime = intdiv($encodedTime, 60);

        $hour = $encodedTime % 24;
        $encodedTime = intdiv($encodedTime, 24);

        $day = ($encodedTime % 31) + 1;
        $encodedTime = intdiv($encodedTime, 31);

        $month = ($encodedTime % 12) + 1;
        $year = intdiv($encodedTime, 12) + 2000;

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }

    private function mapPunchToCheckType(int $punch): string
    {
        return match ($punch) {
            1, 5 => 'O',
            default => 'I',
        };
    }

    /**
     * Build and send one command packet, then read and return the device response.
     */
    private function sendCommand(int $cmd, string $data = '', ?int $replyId = null): array
    {
        if ($replyId !== null) {
            $this->replyId = $replyId & 0xFFFF;
        } elseif ($cmd === self::CMD_CONNECT) {
            $this->replyId = 0;
        } else {
            $this->replyId = ($this->replyId + 1) & 0xFFFF;
        }

        // Build ZK packet: 8-byte header (cmd+checksum+session+reply) + optional data.
        // Compute checksum with the checksum field zeroed first, then insert it.
        $zkPacket = pack('vvvv', $cmd, 0, $this->sessionId, $this->replyId) . $data;
        $checksum = $this->checksum16($zkPacket);
        $zkPacket = pack('vvvv', $cmd, $checksum, $this->sessionId, $this->replyId) . $data;

        // ZKTeco TCP frame: fixed start bytes 50 50 82 7D + 4-byte LE payload length + packet.
        $frame = self::TCP_HEADER . pack('V', strlen($zkPacket)) . $zkPacket;

        if (@fwrite($this->socket, $frame) === false) {
            throw new RuntimeException('Failed to write to device socket');
        }

        $reply = $this->readPacket();

        if ($reply === null) {
            throw new RuntimeException("No response from device for command {$cmd}");
        }

        return $reply;
    }

    /**
     * Read one complete TCP frame from the socket.
     * ZKTeco TCP frame = 4-byte zero preamble + 4-byte LE ZK-packet length + ZK packet.
     * Returns null on timeout / EOF.
     */
    private function readPacket(): ?array
    {
        // Read the 8-byte TCP frame header (magic + LE length)
        $tcpHeader = $this->recvExact(8);
        if ($tcpHeader === null) {
            return null;
        }

        if (substr($tcpHeader, 0, 4) !== self::TCP_HEADER) {
            return null;
        }

        // Bytes 4-7 hold the ZK packet length as a 32-bit little-endian integer.
        $zkLength = $this->unpackUInt32LE($tcpHeader, 4);

        // ZK packet minimum = 8-byte header (cmd + checksum + session_id + reply_id)
        if ($zkLength < 8) {
            return null;
        }

        $zkPacket = $this->recvExact($zkLength);
        if ($zkPacket === null) {
            return null;
        }

        $fields = unpack('vcmd/vchecksum/vsession_id/vreply_id', substr($zkPacket, 0, 8));

        return [
            'cmd'        => $fields['cmd'],
            'checksum'   => $fields['checksum'],
            'session_id' => $fields['session_id'],
            'reply_id'   => $fields['reply_id'],
            'data'       => substr($zkPacket, 8),
        ];
    }

    /**
     * Read exactly $length bytes from the socket, returning null on failure.
     */
    private function recvExact(int $length): ?string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = @fread($this->socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                return null;
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * ZKTeco 16-bit one's-complement checksum.
     */
    private function checksum16(string $data): int
    {
        $sum = 0;
        $len = strlen($data);

        for ($i = 0; $i + 1 < $len; $i += 2) {
            $sum += unpack('v', $data[$i] . $data[$i + 1])[1];
        }

        if ($len % 2 !== 0) {
            $sum += ord($data[$len - 1]);
        }

        // Fold carries into 16 bits then return one's complement
        while ($sum >> 16) {
            $sum = ($sum & 0xFFFF) + ($sum >> 16);
        }

        return (~$sum) & 0xFFFF;
    }

    /**
     * Parse "KEY=VALUE\0" responses returned by CMD_GET_OPTION.
     */
    private function parseOptionValue(string $data): string
    {
        $data = rtrim($data, "\x00\r\n ");
        $pos  = strpos($data, '=');

        return $pos !== false ? substr($data, $pos + 1) : $data;
    }

    /**
     * Safely decode a 32-bit little-endian integer from binary payload.
     */
    private function unpackUInt32LE(string $data, int $offset = 0): int
    {
        if (strlen($data) < $offset + 4) {
            return 0;
        }

        return unpack('V', substr($data, $offset, 4))[1] ?? 0;
    }

    private function makeCommKey(int $ticks = 50): string
    {
        $key = (int) $this->password;
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
        $bytes = array_values(unpack('C4', pack('v2', $halves[1], $halves[0])));

        $mask = $ticks & 0xFF;

        return pack(
            'C4',
            $bytes[0] ^ $mask,
            $bytes[1] ^ $mask,
            $mask,
            $bytes[3] ^ $mask
        );
    }
}
