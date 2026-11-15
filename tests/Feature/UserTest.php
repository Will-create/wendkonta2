<?php

namespace Tests\Feature;

use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_dashboard_requires_authentication(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
