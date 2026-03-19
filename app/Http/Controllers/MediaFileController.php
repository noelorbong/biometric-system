<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;

class MediaFileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $disk = 'uploads';

        $year  = date('Y');
        $month = date('m');
        $day   = date('d');

        $mainFolder      = 'library_files';
        $thumbnailFolder = 'thumbnails';
        $main_folder = '/storage/uploads/';

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
        $thumbnailPath = null;

        if ($type === 'photo') {
            $thumbDirectory = "{$thumbnailFolder}/{$year}/{$month}/{$day}";
            Storage::disk($disk)->makeDirectory($thumbDirectory);

            $thumbnailName = 'thumb_' . $fileName;
            $thumbnailPath = "{$thumbDirectory}/{$thumbnailName}";

            $manager = new ImageManager(new Driver());

            $image = $manager
                ->read(File::get($uploadedFile))
                ->resize(300, 300, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

            Storage::disk($disk)->put(
                $thumbnailPath,
                (string) $image->encode()
            );
        }

        // 💾 Store original file
        Storage::disk($disk)->put(
            $relativePath,
            File::get($uploadedFile)
        );

        // 🗄 Save DB record
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
}
