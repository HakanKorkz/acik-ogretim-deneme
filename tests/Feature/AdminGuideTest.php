<?php

use App\Models\User;

test('misafir kullanım rehberi için girişe yönlendirilir', function () {
    $this->get(route('admin.guide'))->assertRedirect(route('login'));
});

test('yönetici olmayan kullanıcı kullanım rehberine erişemez', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.guide'))
        ->assertForbidden();
});

test('yönetici kullanım rehberini güncel sınav ayarlarıyla görür', function () {
    config()->set('exam.questions_per_exam', 12);
    config()->set('exam.section_minutes', 20);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.guide'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/guide')
            ->where('exam.questionsPerExam', 12)
            ->where('exam.sectionMinutes', 20)
            ->has('exam', 6));
});
