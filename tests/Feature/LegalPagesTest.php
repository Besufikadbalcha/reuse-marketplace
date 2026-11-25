<?php

test('privacy policy page renders starter content', function () {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Starter content for review and customization');
});

test('terms and conditions page renders starter content', function () {
    $this->get(route('terms-and-conditions'))
        ->assertOk()
        ->assertSee('Terms &amp; Conditions', false)
        ->assertSee('Starter content for review and customization');
});
