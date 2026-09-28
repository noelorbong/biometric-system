<?php

namespace Tests\Unit;

use App\Services\ZKTecoSdkService;
use App\Services\ZKTecoService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ZKTecoSdkUserDownloadTest extends TestCase
{
    public function test_user_download_uses_sdk_without_attempting_legacy_tcp_commands(): void
    {
        $users = [['uid' => 123, 'pin' => '00123', 'name' => 'Test', 'password' => '', 'privilege' => 14, 'card' => 0]];
        $sdk = $this->createMock(ZKTecoSdkService::class);
        $sdk->expects($this->once())->method('request')->with('users')->willReturn($users);
        $service = new ZKTecoService('127.0.0.1');
        (new ReflectionProperty($service, 'sdk'))->setValue($service, $sdk);

        $this->assertSame($users, $service->getUsers());
    }
}
