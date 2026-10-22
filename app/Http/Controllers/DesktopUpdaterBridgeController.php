<?php

namespace App\Http\Controllers;

use Native\Laravel\Client\Client;

class DesktopUpdaterBridgeController extends Controller
{
    public function handle(string $action = 'status')
    {
        abort_unless(config('nativephp-internal.running'), 404);
        abort_unless(in_array($action, ['status', 'check', 'download', 'install'], true), 404);

        try {
            $client = new Client;
            $response = $action === 'status'
                ? $client->get('manual-updater/status')
                : $client->post('manual-updater/'.$action);
            if (! $response->successful()) {
                return response()->json(['message' => $response->json('message') ?: 'The desktop updater is unavailable. Rebuild the installer with the updater extension.'], 503);
            }

            return response()->json($response->json());
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to contact the desktop updater. Please retry.'], 503);
        }
    }
}
