<?php

// Uses a dedicated in-memory database; never modifies application data.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'database.default' => 'username_test',
    'database.connections.username_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    'session.driver' => 'array',
    'cache.default' => 'array',
]);

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->string('name');
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
});
User::withoutEvents(fn () => User::create(['name' => 'Legacy', 'email' => 'legacy@example.com', 'password' => 'password123']));
$migration = require __DIR__.'/../../database/migrations/2026_11_19_000001_add_username_and_make_user_email_optional.php';
$migration->up();
check(User::first()->email === 'legacy@example.com', 'Migration must preserve existing accounts.');
User::withoutEvents(function () {
    User::create(['name' => 'Username only', 'username' => 'sample.user', 'email' => null, 'password' => 'password123']);
    User::create(['name' => 'Another', 'username' => 'another', 'email' => null, 'password' => 'password123']);
});
check(DB::table('users')->whereNull('email')->count() === 2, 'Multiple users may omit email.');

$controller = new class extends App\Http\Controllers\Auth\LoginController {
    public function loginCredentials(array $payload): array
    {
        $request = Request::create('/login', 'POST', $payload);
        $this->validateLogin($request);
        return $this->credentials($request);
    }
};
foreach ([
    ['login' => 'sample.user'],
    ['username' => 'sample.user'],
    ['login' => ' sample.user '],
    ['login' => 'legacy@example.com'],
    ['email' => 'legacy@example.com'],
] as $payload) {
    $credentials = $controller->loginCredentials($payload + ['password' => 'password123']);
    check(Auth::guard('web')->validate($credentials), 'Expected username/email login to succeed.');
}
check(!Auth::guard('web')->validate($controller->loginCredentials(['login' => 'sample.user', 'password' => 'wrong'])), 'Wrong passwords must fail.');
check(!Auth::guard('web')->validate($controller->loginCredentials(['login' => 'missing', 'password' => 'password123'])), 'Unknown usernames must fail.');
User::withoutEvents(fn () => User::where('username', 'sample.user')->first()->delete());
check(!Auth::guard('web')->validate($controller->loginCredentials(['login' => 'sample.user', 'password' => 'password123'])), 'Deleted users must not authenticate.');

$rules = [
    'username' => ['required_without:email', 'nullable', 'string', 'min:3', 'max:100', 'regex:/\A[a-zA-Z0-9._-]+\z/', 'unique:users,username'],
    'email' => ['nullable', 'email', 'unique:users,email'],
];
check(Validator::make(['username' => 'new.user', 'email' => null], $rules)->passes(), 'Email must be optional with username.');
check(Validator::make(['username' => null, 'email' => 'new@example.com'], $rules)->passes(), 'Email-only accounts remain supported.');
foreach ([['username' => null, 'email' => null], ['username' => 'another'], ['username' => 'invalid@name'], ['username' => 'with space']] as $payload) {
    check(Validator::make($payload, $rules)->fails(), 'Missing, duplicate or invalid identifiers must fail.');
}

echo "Username authentication, legacy email login, optional emails, migration preservation and identifier validation checks passed.\n";
