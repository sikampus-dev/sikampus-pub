<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('revokes existing sanctum tokens and sessions when a user is deactivated via the api', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'dosen', 'status' => 'active']);
    $target->createToken('auth_token');
    DB::table('sessions')->insert([
        'id' => 'stale-session-id',
        'user_id' => $target->id,
        'payload' => 'irrelevant',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/users/{$target->id}", ['status' => 'inactive'])
        ->assertOk();

    expect($target->tokens()->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();
});

it('does not touch tokens or sessions when a user stays active via the api', function () {
    $admin = adminUser();
    $target = User::factory()->create(['role' => 'dosen', 'status' => 'active']);
    $target->createToken('auth_token');
    DB::table('sessions')->insert([
        'id' => 'still-valid-session-id',
        'user_id' => $target->id,
        'payload' => 'irrelevant',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/users/{$target->id}", ['status' => 'active', 'name' => $target->name])
        ->assertOk();

    expect($target->tokens()->count())->toBe(1);
    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeTrue();
});
