<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\MediaFileController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

config(['nativephp-internal.running' => true]);
Storage::fake('uploads');
$path = 'desktop-logo-check-'.bin2hex(random_bytes(6)).'.png';
$legacy = public_path('uploads/'.$path);
$controller = new MediaFileController;
$settingsMethod = new ReflectionMethod(AppSettingController::class, 'resolveGeneralSettings');
try {
    Storage::disk('uploads')->put($path, 'persistent-logo');
    check($controller->desktopUpload($path)->getFile()->getContent() === 'persistent-logo', 'Persistent logo was not served');
    if (!is_dir(dirname($legacy))) mkdir(dirname($legacy), 0777, true);
    file_put_contents($legacy, 'legacy-logo');
    check($controller->desktopUpload($path)->getFile()->getContent() === 'persistent-logo', 'Persistent logo must take priority');
    Storage::disk('uploads')->delete($path);
    check($controller->desktopUpload($path)->getFile()->getContent() === 'legacy-logo', 'Legacy logo fallback failed');
    foreach (['../index.php', '../../.env', 'missing-logo.png'] as $invalid) {
        try { $controller->desktopUpload($invalid); throw new RuntimeException('Unsafe or missing path served'); }
        catch (HttpException $e) { check($e->getStatusCode() === 404, 'Expected 404'); }
    }
    foreach (['/uploads/'.$path, '/storage/uploads/'.$path] as $oldPath) {
        $settings = $settingsMethod->invoke(new AppSettingController, ['company_school_logo'=>$oldPath, 'biometric_dtr_signatory_signature'=>$oldPath]);
        check($settings['company_school_logo'] === '/desktop-uploads/'.$path, 'Logo URL was not repaired');
        check($settings['biometric_dtr_signatory_signature'] === '/desktop-uploads/'.$path, 'Signature URL was not repaired');
    }
    config(['nativephp-internal.running'=>false]);
    $settings = $settingsMethod->invoke(new AppSettingController, ['company_school_logo'=>'/uploads/'.$path]);
    check($settings['company_school_logo'] === '/uploads/'.$path, 'Web URL changed');
    try { $controller->desktopUpload($path); throw new RuntimeException('Desktop route exposed on web'); }
    catch (HttpException $e) { check($e->getStatusCode() === 404, 'Expected web 404'); }
    echo "Desktop logo storage, legacy fallback, URL repair and path restrictions passed.\n";
} finally {
    Storage::disk('uploads')->delete($path);
    if (is_file($legacy)) unlink($legacy);
}
