<?php

test('uygulama ana sayfası dersleri listeler', function () {
    $response = $this->get(route('courses.index'));

    $response->assertOk();
});
