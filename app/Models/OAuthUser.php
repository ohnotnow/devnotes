<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * Passport's view of a User, used only by the MCP endpoint's OAuth guard.
 *
 * Same users table, same rows. It cannot extend User: Sanctum's and
 * Passport's HasApiTokens traits declare createToken()/tokens()/
 * withAccessToken() with incompatible signatures, and PHP rejects the
 * override. So Sanctum keeps User, Passport gets this standalone class.
 */
class OAuthUser extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';
}
