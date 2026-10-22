<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Native\Laravel\Facades\App as DesktopApp;
use Native\Laravel\Facades\Shell;

class DesktopUpdateController extends Controller
{
    private function repository(): string
    {
        $owner = config('nativephp.updater.providers.github.owner') ?: 'noelorbong';
        $repo = config('nativephp.updater.providers.github.repo') ?: 'biometric-system-releases';
        abort_unless(preg_match('/^[a-zA-Z0-9_.-]+$/', $owner) && preg_match('/^[a-zA-Z0-9_.-]+$/', $repo), 503, 'Invalid update repository configuration.');

        return $owner.'/'.$repo;
    }

    public function version()
    {
        abort_unless(config('nativephp-internal.running'), 404);

        try {
            return response()->json(['version' => DesktopApp::version()]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to read the installed version.'], 503);
        }
    }

    public function check()
    {
        abort_unless(config('nativephp-internal.running'), 404);
        $repository = $this->repository();

        try {
            $current = DesktopApp::version();
            $response = Http::acceptJson()->withHeaders(['User-Agent' => 'Biometric-System-Updater'])
                ->connectTimeout(5)->timeout(15)->get('https://api.github.com/repos/'.$repository.'/releases/latest');
            if ($response->status() === 404) {
                return response()->json(['message' => 'No published release is available. The release repository may also be unavailable.'], 404);
            }
            if (! $response->successful()) {
                return response()->json(['message' => 'GitHub could not complete the update check. Please try again later.'], 503);
            }
            $latest = preg_replace('/^v/i', '', (string) $response->json('tag_name'));
            if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $latest)) {
                return response()->json(['message' => 'The latest release has an invalid version tag.'], 422);
            }

            return response()->json([
                'current_version' => $current,
                'latest_version' => $latest,
                'update_available' => version_compare($latest, preg_replace('/^v/i', '', $current), '>'),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to check for updates. Check your internet connection and try again.'], 503);
        }
    }

    public function openRelease()
    {
        abort_unless(config('nativephp-internal.running'), 404);
        Shell::openExternal('https://github.com/'.$this->repository().'/releases/latest');

        return response()->json(['message' => 'Release page opened in your browser.']);
    }
}
