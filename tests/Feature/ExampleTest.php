<?php

test('uygulama ana sayfası açılır', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});
