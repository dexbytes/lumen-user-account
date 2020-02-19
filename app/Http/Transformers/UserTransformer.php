<?php
namespace App\Transformers;


use App\User;
use League\Fractal\TransformerAbstract;
use Illuminate\Support\Facades\Storage;
use App\Transformers\UserTransformer;

class UserTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    
    public function transform(User $user)
    {
        
        return [
            'userId' => (int)$user->id,
            'userName' => (string)$user->name,
            'userEmail' => (string)$user->email,
            'mobileNumber' => (string)$user->phone_number,
            'userStatus' => (int)$user->status,
            'userProfileImage' => (string)$user->profile_image,
        ];
    }

    
}