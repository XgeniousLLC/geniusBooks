<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = Admin::factory()->create([
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'is_active' => true,
        'role' => 'admin',
    ]);

    $this->actingAs($this->admin, 'admin');
});

describe('Admin Management', function () {
    test('admin can view admins index', function () {
        Admin::factory()->count(3)->create();

        $response = $this->get(route('admin.admins.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.admins.index');
        $response->assertSee('Admin Management');
    });

    test('admin can create a new admin', function () {
        $adminData = [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'manager',
            'is_active' => true,
        ];

        $response = $this->post(route('admin.admins.store'), $adminData);

        $response->assertRedirect(route('admin.admins.index'));

        $this->assertDatabaseHas('admins', [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'role' => 'manager',
            'is_active' => true,
        ]);
    });

    test('admin creation validates required fields', function () {
        $response = $this->postJson(route('admin.admins.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    });

    test('admin creation validates unique email', function () {
        Admin::factory()->create(['email' => 'existing@test.com']);

        $response = $this->postJson(route('admin.admins.store'), [
            'name' => 'New Admin',
            'email' => 'existing@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    });

    test('admin can view another admin details', function () {
        $targetAdmin = Admin::factory()->create(['name' => 'Target Admin']);

        $response = $this->get(route('admin.admins.show', $targetAdmin));

        $response->assertStatus(200);
        $response->assertViewIs('admin.admins.show');
        $response->assertSee('Target Admin');
    });

    test('admin can update another admin', function () {
        $targetAdmin = Admin::factory()->create();

        $response = $this->put(route('admin.admins.update', $targetAdmin), [
            'name' => 'Updated Name',
            'email' => 'updated@test.com',
            'role' => 'editor',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.admins.index'));

        $targetAdmin->refresh();
        $this->assertEquals('Updated Name', $targetAdmin->name);
        $this->assertEquals('updated@test.com', $targetAdmin->email);
        $this->assertEquals('editor', $targetAdmin->role);
        $this->assertFalse($targetAdmin->is_active);
    });

    test('admin cannot delete themselves', function () {
        $response = $this->delete(route('admin.admins.destroy', $this->admin));

        $response->assertRedirect(route('admin.admins.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('admins', ['id' => $this->admin->id]);
    });

    test('admin can delete another admin without pages', function () {
        $targetAdmin = Admin::factory()->create();

        $response = $this->delete(route('admin.admins.destroy', $targetAdmin));

        $response->assertRedirect(route('admin.admins.index'));
        $this->assertDatabaseMissing('admins', ['id' => $targetAdmin->id]);
    });

    test('admin cannot delete admin with created pages', function () {
        $targetAdmin = Admin::factory()->create();
        $targetAdmin->createdPages()->create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'content' => 'Content',
            'status' => 'published',
            'updated_by' => $targetAdmin->id,
        ]);

        $response = $this->delete(route('admin.admins.destroy', $targetAdmin));

        $response->assertRedirect(route('admin.admins.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('admins', ['id' => $targetAdmin->id]);
    });

    test('admin can change another admin password', function () {
        $targetAdmin = Admin::factory()->create();

        $response = $this->post(route('admin.admins.change-password', $targetAdmin), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('admin.admins.index'));

        $targetAdmin->refresh();
        $this->assertTrue(Hash::check('newpassword123', $targetAdmin->password));
    });

    test('admin can change own password with current password', function () {
        $response = $this->post(route('admin.admins.change-password', $this->admin), [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('admin.admins.index'));

        $this->admin->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->admin->password));
    });

    test('admin cannot change own password with wrong current password', function () {
        $response = $this->post(route('admin.admins.change-password', $this->admin), [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('current_password');

        $this->admin->refresh();
        $this->assertTrue(Hash::check('password', $this->admin->password));
    });

    test('admin can update own profile', function () {
        $response = $this->post(route('admin.profile.update'), [
            'name' => 'Updated Profile Name',
            'email' => 'updated.profile@test.com',
        ]);

        $response->assertRedirect(route('admin.profile.edit'));

        $this->admin->refresh();
        $this->assertEquals('Updated Profile Name', $this->admin->name);
        $this->assertEquals('updated.profile@test.com', $this->admin->email);
    });

    test('admin index supports search and filtering', function () {
        Admin::factory()->create(['name' => 'John Manager', 'role' => 'manager']);
        Admin::factory()->create(['name' => 'Jane Editor', 'role' => 'editor']);
        Admin::factory()->create(['name' => 'Bob Admin', 'is_active' => false]);

        $response = $this->get(route('admin.admins.index', ['search' => 'John']));
        $response->assertStatus(200);
        $response->assertSee('John Manager');

        $response = $this->get(route('admin.admins.index', ['role' => 'manager']));
        $response->assertStatus(200);
        $response->assertSee('John Manager');

        $response = $this->get(route('admin.admins.index', ['status' => 'inactive']));
        $response->assertStatus(200);
        $response->assertSee('Bob Admin');
    });
});

describe('Admin Access Control', function () {
    test('unauthenticated users cannot access admin management routes', function () {
        auth('admin')->logout();

        $response = $this->get(route('admin.admins.index'));
        $response->assertRedirect(route('admin.login'));
    });

    test('inactive admin cannot access admin management routes', function () {
        $this->admin->update(['is_active' => false]);

        $response = $this->get(route('admin.admins.index'));
        $response->assertRedirect(route('admin.login'));
    });
});
