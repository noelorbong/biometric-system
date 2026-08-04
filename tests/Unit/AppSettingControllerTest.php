<?php

namespace Tests\Unit;

use App\Http\Controllers\AppSettingController;
use Tests\TestCase;

class AppSettingControllerTest extends TestCase
{
    public function test_resolve_general_settings_defaults_signatory_name_to_blank(): void
    {
        $controller = new AppSettingController();
        $method = new \ReflectionMethod($controller, 'resolveGeneralSettings');
        $method->setAccessible(true);

        $settings = $method->invoke($controller, []);

        $this->assertSame('', $settings['biometric_dtr_signatory_name']);
    }
}
