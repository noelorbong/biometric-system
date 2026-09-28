<?php

// Standalone service routing test; no PHPUnit, database or device required.
require __DIR__.'/../../vendor/autoload.php';

$sdk = new class extends App\Services\ZKTecoSdkService {
    public array $calls = [];

    public function __construct() {}

    public function request(string $operation, array $parameters = []): array
    {
        $this->calls[] = [$operation, $parameters];

        return ['written' => true];
    }
};
$service = new App\Services\ZKTecoService('127.0.0.1');
(new ReflectionProperty($service, 'sdk'))->setValue($service, $sdk);
$user = ['uid' => 123, 'badgenumber' => '00123', 'name' => 'Test', 'privilege' => 14];
$service->disableDevice();
$service->setUserInfo($user);
$service->enableDevice();
if ($sdk->calls !== [['write_user', ['user' => $user]]]) {
    throw new RuntimeException('SDK upload was not routed as one self-contained write.');
}
echo "PASS: upload uses SDK without legacy TCP or separate device-disable requests.\n";
