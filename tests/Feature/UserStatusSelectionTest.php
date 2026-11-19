<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Support\AttendanceDatabaseTestCase;

class UserStatusSelectionTest extends AttendanceDatabaseTestCase
{
    public function test_regular_users_cannot_change_status(): void
    {
        $this->actingAs((new User)->forceFill(['id' => 1, 'role' => 0]));
        $this->postJson('/api/user/status/update', ['id' => 2, 'status' => false])
            ->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => 2, 'status' => true]);
    }

    public function test_status_must_be_boolean(): void
    {
        $this->admin();
        $this->postJson('/api/user/status/update', ['id' => 2, 'status' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('users', ['id' => 2, 'status' => true]);
    }

    public function test_status_requires_an_existing_user(): void
    {
        $this->admin();
        $this->postJson('/api/user/status/update', ['id' => 9999, 'status' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('id');
    }
}
