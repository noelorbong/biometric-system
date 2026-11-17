<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\MediaFileController;
use App\Models\MediaFile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

config(['database.default'=>'media_test', 'database.connections.media_test'=>[
    'driver'=>'sqlite', 'database'=>':memory:', 'prefix'=>'',
], 'nativephp-internal.running'=>true]);
Schema::create('users', fn (Blueprint $table) => $table->id());
(require __DIR__.'/../database/migrations/2026_02_09_183632_create_media_files_table.php')->up();
Illuminate\Support\Facades\DB::table('users')->insert(['id'=>1]);
Storage::fake('uploads');

function verifyUpload(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function uploadFixture(MediaFileController $controller, string $bytes, string $name): array {
    $temp = tempnam(sys_get_temp_dir(), 'media-test-');
    try {
        file_put_contents($temp, $bytes);
        $file = new UploadedFile($temp, $name, null, UPLOAD_ERR_OK, true);
        $request = Request::create('/api/media/upload', 'POST', [], [], ['file'=>$file]);
        $request->setUserResolver(fn () => (object) ['id'=>1]);
        $data = MediaFile::withoutEvents(fn () => $controller->upload($request))->getData(true);
        $path = substr($data['path'], strlen('/desktop-uploads/'));
        verifyUpload(Storage::disk('uploads')->get($path) === $bytes, 'Original upload changed or missing');
        return $data;
    } finally { unlink($temp); }
}

$image = imagecreatetruecolor(600, 100);
imagefill($image, 0, 0, imagecolorallocate($image, 50, 100, 150));
ob_start(); imagepng($image); $png = ob_get_clean();
$controller = new MediaFileController;
$data = uploadFixture($controller, $png, 'logo.PNG');
$thumbnail = substr($data['data']['thumbnail'], strlen('/desktop-uploads/'));
$dimensions = getimagesizefromstring(Storage::disk('uploads')->get($thumbnail));
verifyUpload($dimensions[0] === 300 && $dimensions[1] === 50, 'Thumbnail aspect ratio changed');
verifyUpload(str_ends_with($thumbnail, '.png'), 'Thumbnail encoding must match extension');

if (function_exists('imagewebp')) {
    ob_start(); imagewebp($image); $webp = ob_get_clean();
    $data = uploadFixture($controller, $webp, 'logo.webp');
    verifyUpload($data['data']['file_type'] === 'photo', 'WebP was not treated as an image');
}
imagedestroy($image);

$withoutDecoder = new class extends MediaFileController {
    protected function createThumbnail(string $disk, string $contents, string $directory, string $fileName): ?string {
        throw new RuntimeException('Simulated missing WebP decoder');
    }
};
$data = uploadFixture($withoutDecoder, $png, 'signature.webp');
verifyUpload($data['data']['thumbnail'] === '', 'Failed thumbnail should be omitted');
$data = uploadFixture($controller, 'RIFF invalid WebP image', 'invalid.webp');
verifyUpload($data['data']['thumbnail'] === '', 'Undecodable image should not break original upload');
$ini = (new App\Providers\NativeAppServiceProvider)->phpIni();
verifyUpload($ini['upload_max_filesize'] === '20M' && $ini['post_max_size'] === '24M', 'Desktop upload limits missing');
echo "PNG/WebP uploads, original preservation, thumbnail fallback, aspect ratio and desktop limits passed.\n";
