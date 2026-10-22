<?php

namespace App\Providers;

use Native\Laravel\Facades\Window;
use Native\Laravel\Contracts\ProvidesPhpIni;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    // public function boot(): void
    // {
    //     Window::open()
    //         ->showDevTools(false)
    //         ->hideUntilRendered()
    //         ->maximized()
    //         ->frameless()
    //         ->titleBarHidden();
      
    // }

    public function boot(): void
    {
        try {
            app(\App\Services\DesktopDatabaseStartup::class)->migrate();
        } catch (\Throwable $exception) {
            report($exception);
            Window::open()->title('Biometric System')->url('/desktop-startup')->width(720)->height(480);

            return;
        }

        Window::open()
            ->title('Biometric System')
            ->width(1200) // Provide a fallback size
            ->height(800)
            // ->showDevTools(false)
            // ->frameless()
            // ->titleBarHidden()
            ->maximized()  // Call maximized AFTER setting frameless
            ->hideUntilRendered();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
