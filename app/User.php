<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use App\Transformers\UserTransformer;
use Laravel\Passport\HasApiTokens;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $transformer = UserTransformer::class;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'mobile_number',
        'profile_image',
        'status',
        'device_id',
        'device_token',
        'signup_ip',
        'last_login_ip',
        'last_login_date',
        'email_verification_code',
        'reset_password_otp',
        'reset_password_expires_at',
        'remember_token'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    public function devices()
    {
        return $this->hasMany('App\Models\Device','user_id','id');
    }

    public function roles()
    {
        return $this->belongsToMany('App\Models\Users\Role');
    }
}
