<?php

use App\Models\User;

it('flags users who must change their password', function () {
    $regularUser = User::factory()->create()->refresh();
    $temporaryPasswordUser = User::factory()->mustChangePassword()->create();

    expect($regularUser->must_change_password)->toBeFalse();
    expect($temporaryPasswordUser->must_change_password)->toBeTrue();
});
