<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MediaFileController extends Controller
{
    public function desktopUpload(string $path)
    {
        abort_unless(config('nativephp-internal.running'), 404);

        // Older desktop versions saved files under the bundled public folder.
        // Prefer persistent uploads, but keep those existing images accessible.
        foreach ([Storage::disk('uploads')->path(''), public_path('uploads')] as $directory) {
            $root = realpath($directory);
            $file = $root ? realpath($root.DIRECTORY_SEPARATOR.$path) : false;
            if ($root && $file && is_file($file)
                && str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
                return response()->file($file, ['X-Content-Type-Options' => 'nosniff']);
            }
        }

        abort(404);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        // $disk = 'uploads';

        $disk = config('nativephp-internal.running') ? 'uploads' : 'public';

        $year  = date('Y');
        $month = date('m');
        $day   = date('d');

        $mainFolder      = 'library_files';
        $thumbnailFolder = 'thumbnails';
        $main_folder = config('nativephp-internal.running') ? '/desktop-uploads/' : '/uploads/';
        // $main_folder = '/storage/uploads/';

        $uploadedFile = $request->file('file');
        $extension    = strtolower($uploadedFile->getClientOriginalExtension());
        $size         = $uploadedFile->getSize();

        // Determine file category (folder)
        if (in_array($extension, ['jpg','jpeg','png','gif','webp'])) {
            $category = 'photos';
            $type     = 'photo';
        } elseif (in_array($extension, ['pdf','doc','docx','txt','xls','xlsx','ppt','pptx','csv'])) {
            $category = 'documents';
            $type     = 'document';
        } elseif (in_array($extension, ['mp4','avi','mov','mkv','flv','wmv'])) {
            $category = 'videos';
            $type     = 'video';
        } elseif (in_array($extension, ['mp3','wav','aac','flac','ogg'])) {
            $category = 'musics';
            $type     = 'music';
        } else {
            $category = 'others';
            $type     = 'others';
        }

        // 📂 Paths RELATIVE to disk root
        $directory = "{$mainFolder}/{$category}/{$year}/{$month}/{$day}";
        Storage::disk($disk)->makeDirectory($directory);

        $fileName     = Str::uuid() . '.' . $extension;
        $relativePath = "{$directory}/{$fileName}";

        // 🖼 Thumbnail for images
        // Preserve originals when the desktop GD build cannot decode a format.
        $contents = File::get($uploadedFile);
        abort_unless(Storage::disk($disk)->put($relativePath, $contents), 500,
            'Unable to save the uploaded file. Check available disk space and try again.');

        $thumbnailPath = null;
        if ($type === 'photo') {
            try {
                $thumbnailPath = $this->createThumbnail($disk, $contents, "{$thumbnailFolder}/{$year}/{$month}/{$day}", $fileName);
            } catch (\Throwable $exception) {
                Log::warning('Upload thumbnail could not be generated; original file retained.', [
                    'extension' => $extension,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        $media = MediaFile::create([
            'file_type'           => $type,
            'file_extension'      => $extension,
            'original_file_name'  => $uploadedFile->getClientOriginalName(),
            'file_name'           => $main_folder.$relativePath,
            'thumbnail'           => $thumbnailPath?$main_folder.$thumbnailPath:'',
            'file_size'           => $size,
            'status'              => 'active',
            'user_add'            => $request->user()->id,
            'user_last_modify'    => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'path'    => $main_folder.$relativePath,
            'data'    => $media,
        ]);
    }

    protected function createThumbnail(string $disk, string $contents, string $directory, string $fileName): ?string
    {
        $dimensions = @getimagesizefromstring($contents);
        // Bound decoding memory for large source images.
        if (!$dimensions || $dimensions[0] * $dimensions[1] > 16000000) {
            return null;
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($contents)->scaleDown(width: 300, height: 300);
        $path = $directory.'/thumb_'.pathinfo($fileName, PATHINFO_FILENAME).'.png';
        return Storage::disk($disk)->put($path, (string) $image->toPng()) ? $path : null;
    }

}
