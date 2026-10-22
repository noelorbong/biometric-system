<?php

// NativePHP 0.9 loads its compiled plugin. Install our project-owned extension
// after Composer installs dependencies, including during native:build.
$root = dirname(__DIR__);
$target = $root.'/vendor/nativephp/electron/resources/js/electron-plugin/dist/server';
if (! is_file($target.'/api.js')) {
    fwrite(STDERR, "NativePHP compiled API was not found; updater extension was not installed.\n");
    exit(1);
}
$api = file_get_contents($target.'/api.js');
$anchor = 'httpServer.use("/api/app", appRoutes);';
if (! str_contains($api, $anchor)) {
    fwrite(STDERR, "NativePHP API layout changed; review the updater extension before building.\n");
    exit(1);
}
foreach (['manualUpdater.js', 'updateState.js'] as $file) {
    if (! copy($root.'/resources/desktop/'.$file, $target.'/api/'.$file)) exit(1);
}
if (! str_contains($api, 'import manualUpdaterRoutes')) {
    $api = 'import manualUpdaterRoutes from "./api/manualUpdater";'.PHP_EOL.$api;
    $api = str_replace($anchor, $anchor.PHP_EOL.'        httpServer.use("/api/manual-updater", manualUpdaterRoutes);', $api);
    if (file_put_contents($target.'/api.js', $api) === false) exit(1);
}
echo "Desktop updater extension installed.\n";

// NativePHP 0.9 copies public/icon.png, rather than config('nativephp.icon').
$logo = $root.'/public/images/logo/logo.png';
foreach ([$root.'/public/icon.png', $root.'/vendor/nativephp/electron/resources/js/resources/icon.png'] as $icon) {
    if (! copy($logo, $icon)) exit(1);
}

// Its default BrowserWindow icon is Linux-only; apply it on Windows too.
$windowFile = $target.'/api/window.js';
$window = file_get_contents($windowFile);
$linuxOnly = "process.platform === 'linux' ? { icon: state.icon } : {}";
$desktopIcon = "['linux', 'win32'].includes(process.platform) ? { icon: state.icon } : {}";
if (! str_contains($window, $linuxOnly) && ! str_contains($window, $desktopIcon)) {
    fwrite(STDERR, "NativePHP window implementation changed; review the desktop icon patch.\n");
    exit(1);
}
if (file_put_contents($windowFile, str_replace($linuxOnly, $desktopIcon, $window)) === false) exit(1);
echo "Desktop fingerprint icon installed.\n";
