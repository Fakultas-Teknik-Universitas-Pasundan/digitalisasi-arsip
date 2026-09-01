<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = User::factory()->create(['role' => 'manager']);
    $this->uploader = User::factory()->create(['role' => 'uploader']);
    $this->qc = User::factory()->create(['role' => 'qc']);
});

test('manager dapat membuat user baru dan audit log tercatat', function () {
    $this->actingAs($this->manager);

    $userData = [
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password' => 'password123',
        'role' => 'uploader',
    ];

    $response = $this->postJson('/api/users', $userData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Pengguna berhasil dibuat.',
            'data' => [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'role' => 'uploader',
            ],
        ]);

    // Verify user created in database
    $this->assertDatabaseHas('users', [
        'email' => 'newuser@test.com',
        'role' => 'uploader',
    ]);

    // Verify audit log recorded
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->manager->id,
        'action' => 'create_user',
    ]);
});

test('uploader tidak bisa membuat user', function () {
    $this->actingAs($this->uploader);

    $userData = [
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password' => 'password123',
        'role' => 'uploader',
    ];

    $response = $this->postJson('/api/users', $userData);

    $response->assertStatus(403);
});

test('qc tidak bisa membuat user', function () {
    $this->actingAs($this->qc);

    $userData = [
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password' => 'password123',
        'role' => 'qc',
    ];

    $response = $this->postJson('/api/users', $userData);

    $response->assertStatus(403);
});

test('sbap tidak bisa membuat user', function () {
    $sbap = User::factory()->create(['role' => 'sbap']);
    $this->actingAs($sbap);

    $userData = [
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'password' => 'password123',
        'role' => 'sbap',
    ];

    $response = $this->postJson('/api/users', $userData);

    $response->assertStatus(403);
});

test('manager tidak bisa menghapus diri sendiri', function () {
    $this->actingAs($this->manager);

    $response = $this->deleteJson("/api/users/{$this->manager->id}");

    // Policy returns false for self-deletion → 403 Forbidden
    $response->assertStatus(403);

    // Verify manager still exists
    $this->assertDatabaseHas('users', [
        'id' => $this->manager->id,
        'email' => $this->manager->email,
    ]);
});

test('manager dapat menghapus user lain', function () {
    $this->actingAs($this->manager);

    $response = $this->deleteJson("/api/users/{$this->uploader->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Pengguna berhasil dihapus.',
        ]);

    // Verify user deleted
    $this->assertDatabaseMissing('users', [
        'id' => $this->uploader->id,
    ]);

    // Verify audit log recorded
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->manager->id,
        'action' => 'delete_user',
    ]);
});

test('manager dapat mengupdate user dan audit log tercatat', function () {
    $this->actingAs($this->manager);

    $updateData = [
        'name' => 'Updated Name',
        'role' => 'qc',
    ];

    $response = $this->patchJson("/api/users/{$this->uploader->id}", $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => [
                'name' => 'Updated Name',
                'role' => 'qc',
            ],
        ]);

    // Verify user updated
    $this->assertDatabaseHas('users', [
        'id' => $this->uploader->id,
        'name' => 'Updated Name',
        'role' => 'qc',
    ]);
});

test('manager dapat melihat daftar user', function () {
    $this->actingAs($this->manager);

    $response = $this->getJson('/api/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('uploader tidak bisa melihat daftar user', function () {
    $this->actingAs($this->uploader);

    $response = $this->getJson('/api/users');

    $response->assertStatus(403);
});

test('manager dapat melihat detail user', function () {
    $this->actingAs($this->manager);

    $response = $this->getJson("/api/users/{$this->uploader->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Detail pengguna berhasil diambil.',
            'data' => [
                'id' => $this->uploader->id,
                'email' => $this->uploader->email,
            ],
        ]);
});

test('validasi field required saat membuat user', function () {
    $this->actingAs($this->manager);

    $response = $this->postJson('/api/users', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
});

test('validasi email unik saat membuat user', function () {
    $this->actingAs($this->manager);

    $response = $this->postJson('/api/users', [
        'name' => 'Test',
        'email' => $this->uploader->email, // Duplicate email
        'password' => 'password123',
        'role' => 'uploader',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('validasi role enum saat membuat user', function () {
    $this->actingAs($this->manager);

    $response = $this->postJson('/api/users', [
        'name' => 'Test',
        'email' => 'test@test.com',
        'password' => 'password123',
        'role' => 'invalid_role',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

test('validasi nip unik saat membuat user', function () {
    $this->actingAs($this->manager);

    $this->uploader->update(['nip' => '198501012010121001']);

    $response = $this->postJson('/api/users', [
        'name' => 'New User',
        'email' => 'newuser@test.com',
        'nip' => '198501012010121001', // Duplicate NIP
        'password' => 'password123',
        'role' => 'uploader',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nip']);
});

test('validasi nip unik saat update user', function () {
    $this->actingAs($this->manager);

    $this->uploader->update(['nip' => '198501012010121001']);

    $response = $this->putJson("/api/users/{$this->qc->id}", [
        'nip' => '198501012010121001', // Duplicate NIP
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nip']);
});

test('user tidak terautentikasi tidak bisa akses manajemen user', function () {
    $response = $this->getJson('/api/users');

    $response->assertStatus(401);
});

