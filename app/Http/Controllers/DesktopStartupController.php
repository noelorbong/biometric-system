<?php

namespace App\Http\Controllers;

use App\Services\DesktopDatabaseStartup;

class DesktopStartupController extends Controller
{
    public function show()
    {
        abort_unless(config('nativephp-internal.running'), 404);

        return view('desktop-startup');
    }

    public function retry(DesktopDatabaseStartup $startup)
    {
        abort_unless(config('nativephp-internal.running'), 404);

        try {
            $startup->migrate();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect('/desktop-startup')->with('startup_failed', true);
        }

        return redirect('/');
    }
}
