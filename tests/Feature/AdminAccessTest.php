<?php

use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('misafir yönetim paneline girmek isterse giriş sayfasına yönlendirilir', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('giriş sayfası açılır', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login'));
});

test('kayıt sayfası kapalıdır', function () {
    $this->get('/register')->assertNotFound();
});

test('yönetici giriş yapınca panele yönlendirilir', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
});

test('yanlış şifreyle giriş yapılamaz', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'yanlis-sifre'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('yönetici olmayan kullanıcı panele erişemez', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('yönetici paneli, ders listesini ve profili görür', function () {
    $admin = User::factory()->admin()->create();
    ExamSession::factory()->completed(8)->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('stats.completedSessions', 1)
            ->has('recentSessions', 1));

    $this->actingAs($admin)->get(route('admin.courses.index'))->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/profile')
            ->where('auth.user.email', $admin->email)
            ->missing('auth.user.password'));
});

test('yönetici şifresini değiştirebilir', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'yeni-guclu-sifre',
            'password_confirmation' => 'yeni-guclu-sifre',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('yeni-guclu-sifre', $admin->refresh()->password))->toBeTrue();
});

test('admin:create komutu yönetici oluşturur ve mevcut kullanıcıyı yükseltir', function () {
    $this->artisan('admin:create', [
        '--name' => 'Yönetici',
        '--email' => 'yonetici@example.com',
        '--password' => 'guclu-bir-sifre',
    ])->assertSuccessful();

    $admin = User::query()->where('email', 'yonetici@example.com')->sole();

    expect($admin->is_admin)->toBeTrue()
        ->and(Hash::check('guclu-bir-sifre', $admin->password))->toBeTrue();

    $user = User::factory()->create();

    $this->artisan('admin:create', ['--email' => $user->email, '--password' => 'baska-bir-sifre'])
        ->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue()
        ->and(User::query()->count())->toBe(2);
});
