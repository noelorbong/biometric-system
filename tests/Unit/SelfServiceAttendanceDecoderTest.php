<?php

namespace Tests\Unit;

use App\Services\ZKTecoService;
use PHPUnit\Framework\TestCase;

class SelfServiceAttendanceDecoderTest extends TestCase
{
    public function test_it_decodes_a_self_service_reader_record(): void
    {
        $line = "       67\t2026-06-29 22:36:06\t1\t4\t1\t0\r\n";
        $encoded = '';

        foreach (str_split($line) as $character) {
            $value = ord($character) + 2;
            $encoded .= chr(0x78 + (($value >> 4) & 0x0f));
            $encoded .= chr(0x78 + ($value & 0x0f));
        }

        $raw = str_pad($encoded, 256, "\x00");
        $decoder = new ZKTecoService('127.0.0.1');
        $rows = $decoder->parseEncryptedAttendanceDat($raw);

        self::assertCount(1, $rows);
        self::assertSame('67', $rows[0]['pin']);
        self::assertSame('2026-06-29 22:36:06', $rows[0]['check_time']);
        self::assertSame('O', $rows[0]['check_type']);
        self::assertSame(4, $rows[0]['verify_code']);
        self::assertSame(1, $rows[0]['work_code']);
        self::assertSame(0, $rows[0]['reserved']);
    }

    public function test_it_decodes_the_supplied_granding_export(): void
    {
        $path = dirname(__DIR__, 2) . '/CNCHS/5199232160054_AttEncryptLog.dat';

        if (!is_file($path)) {
            self::markTestSkipped('The supplied Granding sample is not present.');
        }

        $decoder = new ZKTecoService('127.0.0.1');
        $rows = $decoder->parseEncryptedAttendanceDat((string) file_get_contents($path));

        self::assertCount(84, $rows);
        self::assertSame('2', $rows[0]['pin']);
        self::assertSame('2026-06-29 20:26:08', $rows[0]['check_time']);
        self::assertSame('O', $rows[0]['check_type']);
    }
}
