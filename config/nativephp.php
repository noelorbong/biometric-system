<?php

$excludedFiles = [
    'content',
    'storage/framework/sessions/*',
    'storage/framework/testing/*',
    'storage/framework/cache/*',
    'storage/logs/*',
    'storage/app/private/*',
    'storage/app/public/*',
    'docs',
    '.agents',
    '.editorconfig',
    '.env-dev',
    '.env-orig',
    '.env-sqlite',
    '.env.bckp',
    '.env.example',
    '.gitattributes',
    '.gitignore',
    '.phpunit.result.cache',
    'resources/css',
    'resources/fonts',
    'resources/js',
    'resources/sass',
    'resources/views/auth',
    'resources/views/home.blade.php',
    'resources/views/welcome.blade.php',
    'public/uploads/*',
    'tests',
    'package.json',
    'package-lock.json',
    'composer.lock',
    'electron.vite.config.js',
    'electron-builder.json',
    'tailwind.config.js',
    'postcss.config.js',
    'vite.config.js',
    'ubuntu-supervisor',
    'build-output.log',
    'phpunit.xml',
    '.phpunit.cache',
    'ReadMe.md',
];

return [
    'icon' => public_path('images/logo/logo.png'),

    'builder' => [
        'config' => [
            // This NativePHP version does not run native:minify during builds.
            // Exclude files from the Laravel subtree at Electron packaging time.
            'files' => array_merge([
                '**/*',
                '!**/.vscode/*',
                '!src/*',
                '!electron.vite.config.{js,ts,mjs,cjs}',
                '!{.eslintignore,.eslintrc.cjs,.prettierignore,.prettierrc.yaml,dev-app-update.yml,CHANGELOG.md,README.md}',
                '!{.env,.env.*,.npmrc,pnpm-lock.yaml}',
                '!resources/app/vendor/spatie/laravel-package-tools/src/Commands/Concerns/AskToStarRepoOnGitHub.php',
                // Keep NativePHP's directory markers; omit runtime contents.
                '!resources/app/storage/{framework/{sessions,testing,cache},logs}/**/!(_native.json)',
            ], array_map(fn ($path) => '!resources/app/'.$path.'{,/**}', $excludedFiles)),
            'productName' => 'Biometric System',
            'description' => 'Biometric System built with NativePHP and Laravel.',
            'win' => [
                'target' => ['nsis'],
                'icon' => public_path('images/logo/logo.png'), // Windows specific (must be at least 256x256)
            ],
            'mac' => [
                'icon' => public_path('images/logo/logo.icns'), // Mac specific
            ],
        ],
    ],
    /**
     * The version of your app.
     * It is used to determine if the app needs to be updated.
     * Increment this value every time you release a new version of your app.
     */
    'version' => env('NATIVEPHP_APP_VERSION', '1.0.0'),

    /**
     * The ID of your application. This should be a unique identifier
     * usually in the form of a reverse domain name.
     * For example: com.nativephp.app
     */
    'app_id' => env('NATIVEPHP_APP_ID', 'com.nativephp.app'),

    /**
     * If your application allows deep linking, you can specify the scheme
     * to use here. This is the scheme that will be used to open your
     * application from within other applications.
     * For example: "nativephp"
     *
     * This would allow you to open your application using a URL like:
     * nativephp://some/path
     */
    'deeplink_scheme' => env('NATIVEPHP_DEEPLINK_SCHEME'),

    /**
     * The author of your application.
     */
    'author' => 'Noel S. Orbong Jr.',

    /**
     * The default service provider for your application. This provider
     * takes care of bootstrapping your application and configuring
     * any global hotkeys, menus, windows, etc.
     */
    'provider' => \App\Providers\NativeAppServiceProvider::class,

    /**
     * A list of environment keys that should be removed from the
     * .env file when the application is bundled for production.
     * You may use wildcards to match multiple keys.
     */
    'cleanup_env_keys' => [
        'AWS_*',
        'GITHUB_*',
        'DO_SPACES_*',
        '*_SECRET',
        'NATIVEPHP_UPDATER_PATH',
        'NATIVEPHP_APPLE_ID',
        'NATIVEPHP_APPLE_ID_PASS',
        'NATIVEPHP_APPLE_TEAM_ID',
    ],

    /**
     * A list of files and folders that should be removed from the
     * final app before it is bundled for production.
     * You may use glob / wildcard patterns here.
     */
    'cleanup_exclude_files' => $excludedFiles,

    /**
     * The NativePHP updater configuration.
     */
    'updater' => [
        /**
         * Whether or not the updater is enabled. Please note that the
         * updater will only work when your application is bundled
         * for production.
         */
        // The application checks releases itself; downloading is user initiated.
        'enabled' => false,

        /**
         * The updater provider to use.
         * Supported: "github", "s3", "spaces"
         */
        'default' => env('NATIVEPHP_UPDATER_PROVIDER', 'spaces'),

        'providers' => [
            'github' => [
                'driver' => 'github',
                'repo' => env('GITHUB_REPO'),
                'owner' => env('GITHUB_OWNER'),
                'token' => env('GITHUB_TOKEN'),
                'vPrefixedTagName' => env('GITHUB_V_PREFIXED_TAG_NAME', true),
                'private' => env('GITHUB_PRIVATE', false),
                'channel' => env('GITHUB_CHANNEL', 'latest'),
                'releaseType' => env('GITHUB_RELEASE_TYPE', 'draft'),
            ],

            's3' => [
                'driver' => 's3',
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION'),
                'bucket' => env('AWS_BUCKET'),
                'endpoint' => env('AWS_ENDPOINT'),
                'path' => env('NATIVEPHP_UPDATER_PATH', null),
            ],

            'spaces' => [
                'driver' => 'spaces',
                'key' => env('DO_SPACES_KEY_ID'),
                'secret' => env('DO_SPACES_SECRET_ACCESS_KEY'),
                'name' => env('DO_SPACES_NAME'),
                'region' => env('DO_SPACES_REGION'),
                'path' => env('NATIVEPHP_UPDATER_PATH', null),
            ],
        ],
    ],
];
