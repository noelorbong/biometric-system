<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
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
    private string $userDecodeProfile = 'auto';

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
     * Force a deterministic profile for user text decoding.
     * Supported values: auto, latin_stride_even, latin_stride_odd, utf16le, gbk
     */
    public function setUserDecodeProfile(string $profile): void
    {
        $profile = strtolower(trim($profile));
        $allowed = ['auto', 'latin_stride_even', 'latin_stride_odd', 'utf16le', 'gbk'];

        $this->userDecodeProfile = in_array($profile, $allowed, true) ? $profile : 'auto';
    }

    /**
     * Select a stable decode profile from firmware/product hints.
     */
    public function configureUserDecodeProfile(?string $firmVer, ?string $deviceName = null, ?string $produceKind = null): void
    {
        $signature = strtolower(trim(($firmVer ?? '') . ' ' . ($deviceName ?? '') . ' ' . ($produceKind ?? '')));

        if ($signature === '') {
            $this->setUserDecodeProfile('auto');

            return;
        }

        // Older non-TFT firmware variants frequently store user-id/name with interleaved bytes.
        if (str_contains($signature, 'tft')) {
            $this->setUserDecodeProfile('auto');

            return;
        }

        if (
            str_contains($signature, 'face')
            || str_contains($signature, 'iface')
            || str_contains($signature, 'speedface')
            || str_contains($signature, 'linux')
            || str_contains($signature, 'android')
            || str_contains($signature, 'granding')
        ) {
            $this->setUserDecodeProfile('latin_stride_even');

            return;
        }

        $this->setUserDecodeProfile('auto');
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
            '~OEMVendor' => 'Manufacturer',
            'UserCount' => 'UserCount',
            'FPCount' => 'FPCount',
            'FaceCount' => 'FaceCount',
            'ManagerCount' => 'ManagerCount',
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
            $attempts = [
                // Direct cmd9 is fast on FA1-PRO and some GT firmware variants.
                ['label' => 'direct:cmd9', 'read' => fn (): string => $this->readUserDataDirect()],
                ['label' => 'buffer:fct5:ext0', 'read' => fn (): string => $this->readWithBuffer(self::CMD_USERTEMP_RRQ, 5, 0)],
                ['label' => 'buffer:fct0:ext0', 'read' => fn (): string => $this->readWithBuffer(self::CMD_USERTEMP_RRQ, 0, 0)],
                ['label' => 'buffer:fct0:ext1', 'read' => fn (): string => $this->readWithBuffer(self::CMD_USERTEMP_RRQ, 0, 1)],
                ['label' => 'buffer:fct1:ext0', 'read' => fn (): string => $this->readWithBuffer(self::CMD_USERTEMP_RRQ, 1, 0)],
                ['label' => 'buffer:fct5:ext1', 'read' => fn (): string => $this->readWithBuffer(self::CMD_USERTEMP_RRQ, 5, 1)],
                ['label' => 'wrrq:0901', 'read' => fn (): string => $this->readUserDataByWrrq(hex2bin('0901000000000000000000'))],
                ['label' => 'wrrq:0109', 'read' => fn (): string => $this->readUserDataByWrrq(hex2bin('0109000000000000000000'))],
                ['label' => 'wrrq:pack-cvvv', 'read' => fn (): string => $this->readUserDataByWrrq(pack('cvVV', 1, self::CMD_USERTEMP_RRQ, 0, 0))],
            ];

            $lastRaw = '';
            $bestParsed = [];
            $bestQualityScore = -1;
            $bestRank = -1;

            foreach ($attempts as $attempt) {
                $label = (string) ($attempt['label'] ?? 'unknown');
                $reader = $attempt['read'] ?? null;

                if (!is_callable($reader)) {
                    continue;
                }

                $raw = $reader();

                if ($raw === '') {
                    Log::debug('ZKTeco getUsers attempt returned empty payload', [
                        'ip' => $this->ip,
                        'label' => $label,
                    ]);
                    continue;
                }

                $lastRaw = $raw;
                $parsed = $this->parseUserRecords($raw);

                Log::debug('ZKTeco getUsers attempt payload stats', [
                    'ip' => $this->ip,
                    'label' => $label,
                    'raw_bytes' => strlen($raw),
                    'parsed_rows' => count($parsed),
                ]);

                if ($parsed !== []) {
                    $qualityScore = $this->scoreUserListQuality($parsed);
                    $rank = $this->rankUserList($parsed, $qualityScore);
                    $parsedCount = count($parsed);
                    $rawBytes = strlen($raw);
                    $likelyTruncated = $this->isLikelyTruncatedUserPayload($rawBytes, $parsedCount, $label);
                    
                    // Store best result by blended score (quality + capped row count)
                    if (
                        $rank > $bestRank
                        || ($rank === $bestRank && $qualityScore > $bestQualityScore)
                        || ($rank === $bestRank && $qualityScore === $bestQualityScore && $parsedCount > count($bestParsed))
                    ) {
                        $bestRank = $rank;
                        $bestQualityScore = $qualityScore;
                        $bestParsed = $parsed;
                    }

                    // Fast return for clearly valid datasets.
                    if (
                        $parsedCount >= 5
                        && $qualityScore >= 7
                        && !$likelyTruncated
                        && $parsedCount >= count($bestParsed)
                    ) {
                        Log::debug('ZKTeco returning users based on quality score', [
                            'ip' => $this->ip,
                            'label' => $label,
                            'record_count' => $parsedCount,
                            'quality_score' => $qualityScore,
                            'rank' => $rank,
                        ]);
                        return $parsed;
                    }

                    if ($parsedCount >= 5 && $qualityScore >= 7 && $likelyTruncated) {
                        Log::debug('ZKTeco deferring fast return due to likely truncated payload', [
                            'ip' => $this->ip,
                            'label' => $label,
                            'record_count' => $parsedCount,
                            'raw_bytes' => $rawBytes,
                            'quality_score' => $qualityScore,
                            'rank' => $rank,
                        ]);
                    }

                    // FA1/older firmware sometimes scores ~5-6 but with stable full row count.
                    if (
                        $parsedCount >= 8
                        && $qualityScore >= 5
                        && !$likelyTruncated
                        && $parsedCount >= count($bestParsed)
                    ) {
                        Log::debug('ZKTeco returning users based on stable row count', [
                            'ip' => $this->ip,
                            'label' => $label,
                            'record_count' => $parsedCount,
                            'quality_score' => $qualityScore,
                            'rank' => $rank,
                        ]);
                        return $parsed;
                    }

                    // Keep probing other variants when result looks suspiciously small or low quality
                    continue;
                }
            }

            // Return best result found if quality score reached minimum threshold
            if ($bestParsed !== [] && $bestQualityScore >= 5) {
                Log::debug('ZKTeco returning best parsed users', [
                    'ip' => $this->ip,
                    'record_count' => count($bestParsed),
                    'quality_score' => $bestQualityScore,
                    'rank' => $bestRank,
                ]);
                return $bestParsed;
            }

            return $bestParsed;
        } finally {
            $this->sendCommand(self::CMD_ENABLEDEVICE);
        }
    }

    /**
     * Detect payload sizes that likely indicate a transfer cap rather than the
     * true full user dataset. This prevents an early return from a high-quality
     * but incomplete parse and allows trying fallback read variants.
     */
    private function isLikelyTruncatedUserPayload(int $rawBytes, int $parsedCount, string $label): bool
    {
        if ($rawBytes <= 0 || $parsedCount < 100) {
            return false;
        }

        // Exact 64 KiB boundaries are a common symptom of capped transfers.
        if (in_array($rawBytes, [65536, 131072, 196608], true)) {
            return true;
        }

        if (str_starts_with($label, 'direct:') && $rawBytes % 65536 === 0) {
            return true;
        }

        return false;
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
        $reachedExpected = $expectedSize <= 0;

        while (true) {
            $reply = $this->readPacket();
            if ($reply === null) {
                break;
            }

            if ($reply['cmd'] === self::CMD_DATA) {
                $raw .= $reply['data'] ?? '';

                if (!$reachedExpected && $expectedSize > 0 && strlen($raw) >= $expectedSize) {
                    $reachedExpected = true;
                }

                continue;
            }

            if ($reply['cmd'] === self::CMD_PREPARE_DATA) {
                $raw .= $this->receiveChunkedData(
                    $this->unpackUInt32LE($reply['data'] ?? ''),
                    $reply['reply_id']
                );
                $reachedExpected = true;
                continue;
            }

            if ($reply['cmd'] === self::CMD_ACK_OK && $reply['reply_id'] === $replyId) {
                break;
            }

            // For unknown-size transfers, or once we have already consumed the
            // declared size, stop when leaving data frames.
            if ($expectedSize <= 0 || $reachedExpected) {
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
            return $this->collectDataFrames($reply['data'] ?? '', $reply['reply_id']);
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
                self::CMD_DATA         => $this->collectDataFrames($chunkReply['data'] ?? '', $chunkReply['reply_id']),
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

        $bestUsers = [];
        $bestQuality = -1;
        $bestCount = 0;
        $bestRank = -1;
        $bestMeta = [];
        $candidateIndex = 0;
        foreach ($this->buildUserPayloadCandidates($raw) as $payload) {
            $recordSize = $this->detectUserRecordSize($payload);

            if ($recordSize === null) {
                $candidateIndex++;
                continue;
            }

            $users = [];
            $total = strlen($payload);
            $debugLogRawRecord = null;

            // For 73-byte records, detect the byte offset of the 72-byte user data block.
            // FA1-PRO: STX at byte 0, data starts at byte 1 (offset = 1).
            // GT100/GT200: extra header prefix, data may start at a higher offset.
            $dataOffset73 = ($recordSize === 73) ? $this->detect73ByteUserDataOffset($payload) : 0;

            for ($offset = 0; $offset + $recordSize <= $total; $offset += $recordSize) {
                $record = substr($payload, $offset, $recordSize);
                
                // Capture first raw record for diagnostics
                if ($offset === 0 && $debugLogRawRecord === null) {
                    $debugLogRawRecord = bin2hex(substr($record, 0, min(72, strlen($record))));
                }


                if ($recordSize === 73) {
                    // Verify the expected STX marker exists at (dataOffset73 - 1).
                    // If not present, skip truly invalid records (e.g. padding/filler).
                    $stxPos = $dataOffset73 - 1;
                    if ($stxPos >= 0 && ord($record[$stxPos]) !== 2) {
                        continue;
                    }

                    $record = substr($record, $dataOffset73);
                }

                if ($recordSize === 28) {
                    $uid = unpack('v', substr($record, 0, 2))[1] ?? 0;
                    $privilege = ord(substr($record, 2, 1) ?: "\x00");
                    $passwordBytes = substr($record, 3, 5);
                    $nameBytes = substr($record, 8, 8);
                    $password = $this->decodeTextField($passwordBytes);
                    $name = $this->decodeNameField($nameBytes);
                    $card = unpack('V', substr($record, 16, 4))[1] ?? 0;
                    $pin = (string) (unpack('V', substr($record, 24, 4))[1] ?? 0);
                } else {
                    $uid = unpack('v', substr($record, 0, 2))[1] ?? 0;
                    $privilege = ord(substr($record, 2, 1) ?: "\x00");
                    $passwordBytes = substr($record, 3, 8);
                    
                    // Try multiple offsets for name field (device firmware variations)
                    $nameBytes = '';
                    $nameOffset = 11; // fallback default
                    foreach ([11, 13, 15, 35, 3, 5, 7, 20, 25, 30, 40, 45, 50] as $testOffset) {
                        if ($testOffset + 24 > strlen($record)) continue;
                        $candidate = substr($record, $testOffset, 24);
                        if (strlen($candidate) === 24 && $candidate !== str_repeat("\x00", 24)) {
                            $nameBytes = $candidate;
                            $nameOffset = $testOffset;
                            break;
                        }
                    }
                    // Fallback to offset 11 if all are empty
                    if ($nameBytes === '') {
                        $nameBytes = substr($record, 11, 24);
                        $nameOffset = 11;
                    }
                    
                    $card = unpack('V', substr($record, 35, 4))[1] ?? 0;
                    $pinBytes = substr($record, 48, 24);

                    $password = $this->decodeTextField($passwordBytes);
                    $name = $this->decodeNameField($nameBytes);
                    $pin = $this->decodePinField($pinBytes);
                    
                    // Debug: Log full record structure to find actual name location
                    if ($uid > 0 && count($users) < 5) {
                        $offsetScan = [];
                        for ($i = 0; $i <= 48; $i += 2) {
                            if ($i + 24 <= strlen($record)) {
                                $testBytes = substr($record, $i, 24);
                                $offsetScan[$i] = bin2hex($testBytes);
                            }
                        }
                        Log::debug('RECORD_FULL_SCAN', [
                            'uid' => $uid,
                            'record_size' => $recordSize,
                            'record_hex' => substr(bin2hex($record), 0, 150),
                            'uid_at_0_2' => bin2hex(substr($record, 0, 2)),
                            'found_name_at_offset' => $nameOffset,
                            'found_name_hex' => bin2hex($nameBytes),
                            'decoded_name' => $name,
                            'offset_samples' => $offsetScan,
                        ]);
                    }
                }

                if ($pin === '' && $uid > 0) {
                    $pin = (string) $uid;
                }

                // GT100/GT200 nonstandard 73-byte layout can carry unreliable UID bytes
                // while PIN is decoded correctly. In that layout, prefer numeric PIN as UID.
                if (
                    $recordSize === 73
                    && $dataOffset73 > 1
                    && preg_match('/^\d+$/', $pin) === 1
                ) {
                    $uid = (int) $pin;
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

            if ($users !== []) {
                $users = $this->repairUnreliableUserIds($users);
                $users = $this->repairSequentialUidFromPin($users);
                $quality = $this->scoreUserListQuality($users);
                $rank = $this->rankUserList($users, $quality);

                Log::debug('PARSED_USER_RECORDS', [
                    'payload_candidate_index' => $candidateIndex,
                    'record_size' => $recordSize,
                    'total_users' => count($users),
                    'quality_score' => $quality,
                    'rank' => $rank,
                    'payload_length' => strlen($payload),
                    'expected_records' => floor(strlen($payload) / $recordSize),
                    'payload_start_hex' => substr(bin2hex($payload), 0, 48),
                    'first_raw_record_hex' => $debugLogRawRecord,
                    'first_user_name' => $users[0]['name'] ?? '(none)',
                    'first_user_uid' => $users[0]['uid'] ?? 0,
                ]);

                $count = count($users);

                if (
                    $rank > $bestRank
                    || ($rank === $bestRank && $quality > $bestQuality)
                    || ($rank === $bestRank && $quality === $bestQuality && $count > $bestCount)
                ) {
                    $bestUsers = $users;
                    $bestQuality = $quality;
                    $bestCount = $count;
                    $bestRank = $rank;
                    $bestMeta = [
                        'payload_candidate_index' => $candidateIndex,
                        'record_size' => $recordSize,
                        'quality_score' => $quality,
                        'rank' => $rank,
                    ];
                }

                // High-confidence parse; no need to keep scanning payload candidates.
                if (count($users) >= 5 && $quality >= 8) {
                    return $users;
                }
            }
            
            $candidateIndex++;
        }

        if ($bestUsers !== []) {
            Log::debug('PARSED_USER_RECORDS_SELECTED_BEST', [
                'candidate' => $bestMeta,
                'record_count' => $bestCount,
                'quality_score' => $bestQuality,
                'rank' => $bestRank,
            ]);
        }

        return $bestUsers;
    }

    /**
     * Some device layouts decode PIN reliably but produce unusable UID bytes
     * (mostly 0 or large garbage values). When this pattern is dominant,
     * normalize UID from numeric PIN for consistency in UI/import mapping.
     *
     * @param array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}> $users
     * @return array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}>
     */
    private function repairUnreliableUserIds(array $users): array
    {
        if (count($users) < 5) {
            return $users;
        }

        $numericPinCount = 0;
        $invalidUidCount = 0;

        foreach ($users as $user) {
            $pin = (string) ($user['pin'] ?? '');

            if (preg_match('/^\d+$/', $pin) !== 1) {
                continue;
            }

            $numericPinCount++;
            $uid = (int) ($user['uid'] ?? 0);

            if ($uid <= 0 || $uid > 9999) {
                $invalidUidCount++;
            }
        }

        if ($numericPinCount < 5) {
            return $users;
        }

        // Apply only when UID corruption is clearly dominant.
        if (($invalidUidCount / $numericPinCount) < 0.6) {
            return $users;
        }

        foreach ($users as &$user) {
            $pin = (string) ($user['pin'] ?? '');

            if (preg_match('/^\d+$/', $pin) === 1) {
                $user['uid'] = (int) $pin;
            }
        }
        unset($user);

        return $users;
    }

    /**
     * Detect when the uid field contains sequential slot numbers (1, 2, 3 … N) while
     * the PIN field contains the real, non-sequential user identifiers.  This layout
     * appears on Granding GT200 devices whose direct:cmd9 payload uses slot-indexed
     * UIDs but stores the true user number as a null-terminated string in the PIN
     * field.  When detected, uid is overwritten with the numeric PIN value.
     *
     * @param array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}> $users
     * @return array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}>
     */
    private function repairSequentialUidFromPin(array $users): array
    {
        if (count($users) < 5) {
            return $users;
        }

        // Collect uids and check for strict 1-based sequential
        $uids = array_column($users, 'uid');
        sort($uids);
        for ($i = 0, $n = count($uids); $i < $n; $i++) {
            if ($uids[$i] !== $i + 1) {
                return $users; // UIDs are not slot-sequential, nothing to repair
            }
        }

        // All UIDs are sequential – now verify PINs are all numeric
        $numericCount = 0;
        $pinInts      = [];
        foreach ($users as $user) {
            $pin = (string) ($user['pin'] ?? '');
            if (preg_match('/^\d+$/', $pin) === 1) {
                $numericCount++;
                $pinInts[] = (int) $pin;
            }
        }

        if ($numericCount < count($users) * 0.9) {
            return $users; // Not enough numeric PINs
        }

        // If PINs are ALSO sequential (1,2,3…) there is nothing to distinguish;
        // leave the record as-is to avoid false positives.
        $sorted = $pinInts;
        sort($sorted);
        $pinSequential = true;
        for ($i = 0, $n = count($sorted); $i < $n; $i++) {
            if ($sorted[$i] !== $i + 1) {
                $pinSequential = false;
                break;
            }
        }

        if ($pinSequential) {
            return $users;
        }

        // UIDs are slot-sequential but PINs differ → use PIN as the real UID
        foreach ($users as &$user) {
            $pin = (string) ($user['pin'] ?? '');
            if (preg_match('/^\d+$/', $pin) === 1) {
                $user['uid'] = (int) $pin;
            }
        }
        unset($user);

        return $users;
    }

    /**
     * Score user list quality by analyzing name characteristics.
     * Higher score = better quality names (less garbage, more readable).
     * Score 0-10 scale.
     * 
     * @param array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}> $users
     */
    private function scoreUserListQuality(array $users): int
    {
        if (count($users) === 0) {
            return 0;
        }

        $sample = array_slice($users, 0, min(10, count($users))); // Check first 10 users
        $nameQualitySum = 0;

        foreach ($sample as $user) {
            $name = $user['name'] ?? '';
            
            if ($name === '' || strpos($name, 'NN-') === 0) {
                // No real name (fallback generated name)
                $nameQualitySum += 1;
                continue;
            }

            $score = 0;

            // Reward length (real names are typically longer than garbage)
            $len = strlen($name);
            if ($len >= 5) $score += 2;
            if ($len >= 10) $score += 1;
            if ($len >= 15) $score += 1;

            // Reward uppercase letters (names often have them)
            $upCount = preg_match_all('/[A-Z]/', $name);
            if ($upCount > 0) $score += 1;
            if ($upCount > 2) $score += 1;

            // Reward simple spaces and punctuation (comma, period - typical in names)
            if (preg_match('/[\s,.]/', $name)) $score += 1;

            // Penalize numeric-only or mostly numeric
            $digitRatio = preg_match_all('/[0-9]/', $name) / (strlen($name) ?: 1);
            if ($digitRatio > 0.5) {
                $score -= 2;
            }
            if ($digitRatio === 1.0) {
                // All digits - garbage
                $score = 0;
            }

            // Penalize control chars and high bytes (indicates wrong encoding)
            if (preg_match('/[\x00-\x08\x0E-\x1F\x7F-\xFF]/', $name)) {
                $score -= 1;
            }

            $nameQualitySum += max(0, $score);
        }

        $avgQuality = (int) ($nameQualitySum / count($sample));
        return min(10, max(0, $avgQuality));
    }

    /**
     * Blend name quality with a small count bonus so tiny high-quality subsets
     * do not beat fuller valid user lists.
     *
     * @param array<int, array{uid:int,pin:string,name:string,password:string,privilege:int,card:int}> $users
     */
    private function rankUserList(array $users, ?int $qualityScore = null): int
    {
        $quality = $qualityScore ?? $this->scoreUserListQuality($users);
        $countBonus = min(12, count($users));

        return ($quality * 6) + $countBonus;
    }

    private function buildUserPayloadCandidates(string $raw): array
    {
        $candidates = [$raw];
        $length = strlen($raw);

        if ($length >= 4) {
            $declaredSize = $this->unpackUInt32LE($raw, 0);

            if ($declaredSize > 0 && $declaredSize <= $length - 4) {
                $candidates[] = substr($raw, 4, $declaredSize);
            }
        }

        if ($length >= 8) {
            $declaredSize = $this->unpackUInt32LE($raw, 4);

            if ($declaredSize > 0 && $declaredSize <= $length - 8) {
                $candidates[] = substr($raw, 8, $declaredSize);
            }
        }

        $sizes = [73, 72, 28];
        $final = [];

        foreach ($candidates as $candidate) {
            $final[] = $candidate;

            foreach ($sizes as $size) {
                $mod = strlen($candidate) % $size;

                if ($mod === 0) {
                    // Try scanning for correct STX alignment for 73-byte records
                    if ($size === 73) {
                        for ($align = 1; $align < 73 && $align < strlen($candidate); $align++) {
                            if (ord($candidate[$align]) === 0x02 && (strlen($candidate) - $align) % 73 === 0) {
                                $aligned = substr($candidate, $align);
                                if (strlen($aligned) >= 73) {
                                    $final[] = $aligned;
                                }
                                break;
                            }
                        }
                    }
                    continue;
                }

                $trimmedHead = substr($candidate, $mod);
                if (strlen($trimmedHead) >= $size) {
                    $final[] = $trimmedHead;
                }

                $trimmedTail = substr($candidate, 0, strlen($candidate) - $mod);
                if (strlen($trimmedTail) >= $size) {
                    $final[] = $trimmedTail;
                }
            }
        }

        return array_values(array_unique(array_filter($final, static fn (string $item): bool => $item !== '')));
    }

    private function readUserDataByWrrq(string $payload): string
    {
        $reply = $this->sendCommand(self::CMD_DATA_WRRQ, $payload);

        return match ($reply['cmd']) {
            self::CMD_ACK_OK       => $this->receivePreparedData($reply['reply_id']),
            self::CMD_DATA         => $this->collectDataFrames($reply['data'] ?? '', $reply['reply_id']),
            self::CMD_PREPARE_DATA => $this->receiveChunkedData(
                $this->unpackUInt32LE($reply['data'] ?? ''),
                $reply['reply_id']
            ),
            default                => '',
        };
    }

    private function readUserDataDirect(): string
    {
        $reply = $this->sendCommand(self::CMD_USERTEMP_RRQ);

        return match ($reply['cmd']) {
            self::CMD_ACK_OK       => $this->receivePreparedData($reply['reply_id']),
            self::CMD_DATA         => $this->collectDataFrames($reply['data'] ?? '', $reply['reply_id']),
            self::CMD_PREPARE_DATA => $this->receiveChunkedData(
                $this->unpackUInt32LE($reply['data'] ?? ''),
                $reply['reply_id']
            ),
            default                => '',
        };
    }

    /**
     * Some firmware streams one logical payload across multiple CMD_DATA frames.
     */
    private function collectDataFrames(string $firstChunk, int $replyId): string
    {
        $data = $firstChunk;

        while (true) {
            $next = $this->readPacket();

            if ($next === null) {
                break;
            }

            if ($next['cmd'] === self::CMD_DATA) {
                $data .= $next['data'] ?? '';
                continue;
            }

            if ($next['cmd'] === self::CMD_PREPARE_DATA) {
                $data .= $this->receiveChunkedData(
                    $this->unpackUInt32LE($next['data'] ?? ''),
                    $next['reply_id']
                );
                break;
            }

            if ($next['cmd'] === self::CMD_ACK_OK && $next['reply_id'] === $replyId) {
                break;
            }

            break;
        }

        return $data;
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
     * For 73-byte record payloads, detect the byte offset within each 73-byte slot
     * where the actual 72-byte user data block begins, by scanning for consistent
     * ASCII name characters. Some Granding firmware variants (GT100, GT200) use a
     * different header prefix length than the standard STX-at-byte-0 format.
     */
    private function detect73ByteUserDataOffset(string $payload): int
    {
        // Vote for which byte position within the 73-byte record consistently holds 0x02 (STX).
        // FA1-PRO:     STX at position 0  -> data starts at position 1
        // GT100/GT200: STX at position 11 -> data starts at position 12
        $sampleCount = min(30, (int)(strlen($payload) / 73));
        $stxVotes = [];

        for ($r = 0; $r < $sampleCount; $r++) {
            $record = substr($payload, $r * 73, 73);
            // Only consider positions 0-20 as plausible STX locations
            for ($b = 0; $b <= 20; $b++) {
                if (ord($record[$b]) === 0x02) {
                    $stxVotes[$b] = ($stxVotes[$b] ?? 0) + 1;
                    break; // take first STX found per record
                }
            }
        }

        if (empty($stxVotes)) {
            return 1; // fallback: standard layout
        }

        // Pick the position that appears in the most records
        arsort($stxVotes);
        $stxPos = (int) array_key_first($stxVotes);
        return $stxPos + 1; // data starts immediately after STX
    }

    /**
     * Convert device-provided text into safe UTF-8 for JSON responses.
     */
    private function normalizeTextField(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $value = trim($value);

        // Many devices return UTF-16LE strings (ASCII bytes interleaved with nulls).
        if ($this->looksLikeUtf16($value)) {
            $utf16 = @mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');

            if (is_string($utf16) && $utf16 !== '' && preg_match('//u', $utf16) === 1) {
                return $this->sanitizeDisplayText($utf16);
            }
        }

        if (preg_match('//u', $value) === 1) {
            return $this->sanitizeDisplayText($value);
        }

        if (function_exists('mb_convert_encoding')) {
            foreach (['UTF-16LE', 'UTF-16BE', 'UTF-8', 'GBK', 'GB2312', 'ISO-8859-1', 'Windows-1252'] as $from) {
                $converted = @mb_convert_encoding($value, 'UTF-8', $from);

                if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
                    return $this->sanitizeDisplayText($converted);
                }
            }
        }
        $cleaned = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($cleaned) && $cleaned !== '') {
            return $this->sanitizeDisplayText($cleaned);
        }

        return $this->sanitizeDisplayText(preg_replace('/[^\x20-\x7E]/', '', $value) ?? '');
    }

    private function decodeTextField(string $bytes): string
    {
        return $this->normalizeTextField(rtrim($bytes, "\x00 \r\n\t"));
    }

    private function decodeNameField(string $bytes): string
    {
        $candidates = $this->buildDecodeCandidates($bytes);

        if ($candidates === []) {
            return '';
        }

        $best = '';
        $bestScore = PHP_INT_MIN;

        foreach ($candidates as $candidate) {
            $score = $this->scoreNameCandidate($candidate);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $this->normalizeDecodedName($best);
    }

    private function normalizeDecodedName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        // Some firmware variants leak numeric bytes into the beginning of names
        // (example: "1763VELARDE, GAIL B."). Remove only the prefix when the
        // remainder clearly looks like a human name.
        if (preg_match('/^(\d{3,6})([A-Za-z][A-Za-z ,.-]{3,})$/', $name, $m) === 1) {
            $candidate = trim($m[2]);

            if (
                (str_contains($candidate, ',') || str_contains($candidate, ' '))
                && preg_match('/[A-Za-z]{3,}/', $candidate) === 1
            ) {
                return $candidate;
            }
        }

        return $name;
    }

    private function decodePinField(string $bytes): string
    {
        if ($this->userDecodeProfile === 'latin_stride_even') {
            $fixed = $this->normalizeTextField($this->everySecondByte($bytes, 0));
            if ($fixed !== '') {
                return preg_replace('/[^0-9A-Za-z._-]/u', '', $fixed) ?? $fixed;
            }
        }

        if ($this->userDecodeProfile === 'latin_stride_odd') {
            $fixed = $this->normalizeTextField($this->everySecondByte($bytes, 1));
            if ($fixed !== '') {
                return preg_replace('/[^0-9A-Za-z._-]/u', '', $fixed) ?? $fixed;
            }
        }

        // Some Granding GT devices (e.g. GT200) pack the PIN field as a null-terminated
        // C-string followed by a device identifier string (e.g. "2\0RAISA A. GUIO...").
        // Reading only up to the first null gives the real PIN and discards the trailer.
        $firstNull = strpos($bytes, "\x00");
        if ($firstNull !== false && $firstNull > 0 && $firstNull < 16) {
            $prefix = substr($bytes, 0, $firstNull);
            if (preg_match('/^[0-9A-Za-z._-]+$/', $prefix) === 1) {
                return $prefix;
            }
        }

        $candidates = $this->buildDecodeCandidates($bytes);

        if ($candidates === []) {
            return '';
        }

        $best = '';
        $bestScore = PHP_INT_MIN;

        foreach ($candidates as $candidate) {
            $score = $this->scorePinCandidate($candidate);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best === '') {
            return '';
        }

        $clean = preg_replace('/[^0-9A-Za-z._-]/u', '', $best) ?? '';

        return $clean !== '' ? $clean : $best;
    }

    private function buildDecodeCandidates(string $bytes): array
    {
        $trimmed = rtrim($bytes, "\x00 \r\n\t");

        if ($trimmed === '') {
            return [];
        }

        $candidates = [];

        // For Granding devices, try UTF-16LE FIRST before plain ASCII (empirical observation from hex dumps)
        if (function_exists('mb_convert_encoding')) {
            $utf16Attempt = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');
            if (is_string($utf16Attempt)) {
                $cleaned = $this->sanitizeDisplayText($utf16Attempt);
                if ($cleaned !== '') {
                    $candidates[] = $cleaned;
                }
            }
        }

        // Try plain ASCII/Latin-1 extraction second
        $plainAscii = $this->extractPlainAscii($bytes);
        if ($plainAscii !== '') {
            $candidates[] = $plainAscii;
        }

        if ($this->userDecodeProfile === 'latin_stride_even') {
            $candidates[] = $this->normalizeTextField($this->everySecondByte($bytes, 0));
            $candidates[] = $this->normalizeTextField($trimmed);
            $candidates[] = $this->normalizeTextField($this->everySecondByte($bytes, 1));
        } elseif ($this->userDecodeProfile === 'latin_stride_odd') {
            $candidates[] = $this->normalizeTextField($this->everySecondByte($bytes, 1));
            $candidates[] = $this->normalizeTextField($trimmed);
            $candidates[] = $this->normalizeTextField($this->everySecondByte($bytes, 0));
        } else {
            $candidates[] = $this->normalizeTextField($trimmed);
            $candidates[] = $this->normalizeTextField($this->everySecondByte($bytes, 0));
            $candidates[] = $this->normalizeTextField($this->everySecondByte($bytes, 1));
        }

        if (function_exists('mb_convert_encoding')) {
            $fromEncodings = match ($this->userDecodeProfile) {
                'utf16le' => ['UTF-16LE', 'UTF-16BE', 'GBK', 'GB2312'],
                'gbk' => ['GBK', 'GB2312', 'UTF-16LE', 'UTF-16BE'],
                default => ['UTF-16LE', 'UTF-16BE', 'GBK', 'GB2312'],
            };

            foreach ($fromEncodings as $fromEncoding) {
                $converted = @mb_convert_encoding($bytes, 'UTF-8', $fromEncoding);

                if (is_string($converted)) {
                    $candidates[] = $this->sanitizeDisplayText($converted);
                }
            }
        }

        $ascii = preg_replace('/[^\x20-\x7E]/', '', $bytes) ?? '';
        $candidates[] = $this->sanitizeDisplayText($ascii);

        $candidates = array_values(array_unique(array_filter($candidates, static fn (string $v): bool => $v !== '')));

        return $candidates;
    }

    /**
     * Extract plain ASCII/Latin-1 text from device bytes, matching official Granding app.
     */
    private function extractPlainAscii(string $bytes): string
    {
        $result = '';
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $ch = ord($bytes[$i]);

            // Printable ASCII (space to ~) and extended Latin-1
            if (($ch >= 0x20 && $ch <= 0x7E) || ($ch >= 0xA0 && $ch <= 0xFF)) {
                $result .= $bytes[$i];
            } elseif ($ch === 0x00) {
                // Null terminator marks end of field
                break;
            }
        }

        return trim($result);
    }

    private function everySecondByte(string $bytes, int $start): string
    {
        $result = '';
        $length = strlen($bytes);

        for ($i = $start; $i < $length; $i += 2) {
            $result .= $bytes[$i];
        }

        return $result;
    }

    private function scorePinCandidate(string $value): int
    {
        $len = strlen($value);

        if ($len === 0) {
            return -1000;
        }

        $allowed = preg_match_all('/[0-9A-Za-z._-]/u', $value) ?: 0;
        $digits = preg_match_all('/\d/u', $value) ?: 0;
        $others = max(0, $len - $allowed);

        return ($allowed * 4) + ($digits * 2) - ($others * 5);
    }

    private function scoreNameCandidate(string $value): int
    {
        $len = strlen($value);

        if ($len === 0) {
            return -1000;
        }

        $latin = preg_match_all('/[A-Za-z0-9 ._-]/u', $value) ?: 0;
        $letters = preg_match_all('/[A-Za-z]/u', $value) ?: 0;
        $digits = preg_match_all('/\d/u', $value) ?: 0;
        $nonAscii = preg_match_all('/[^\x00-\x7F]/u', $value) ?: 0;
        $ctrl = preg_match_all('/[[:cntrl:]]/u', $value) ?: 0;
        $punctuation = preg_match_all('/[.,;:\'-]/u', $value) ?: 0;

        $digitOnlyPenalty = 0;
        if ($digits > 0 && $letters === 0) {
            $digitOnlyPenalty = $digits * 3;
        }

        // Boost score for names with letters and real name-like punctuation
        $realNameBonus = 0;
        if ($letters >= 2 && ($punctuation > 0 || preg_match('/\s/', $value))) {
            $realNameBonus = 20;
        }

        return ($latin * 3) + ($letters * 5) - ($nonAscii * 2) - ($ctrl * 5) - $digitOnlyPenalty + $realNameBonus;
    }

    private function looksLikeUtf16(string $value): bool
    {
        $length = strlen($value);

        if ($length < 2) {
            return false;
        }

        $nullBytes = substr_count($value, "\x00");

        // Heuristic: interleaved-null strings are usually UTF-16 from device firmware.
        return $nullBytes > 0 && $nullBytes >= intdiv($length, 4);
    }

    private function sanitizeDisplayText(string $value): string
    {
        $value = str_replace("\x00", '', $value);
        $value = preg_replace('/[[:cntrl:]&&[^\r\n\t]]/u', '', $value) ?? $value;
        $value = trim($value);

        return preg_replace('/\s{2,}/u', ' ', $value) ?? $value;
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
        $value = $pos !== false ? substr($data, $pos + 1) : $data;

        return trim($value, "\x00\r\n ");
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
