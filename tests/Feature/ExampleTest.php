<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('calendar.public'));
});
