<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Closure;

class AppMask
{
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    { 
        $encrypted_m = Crypt::encryptString($request->header('appkey'));
        $encrypted_v = Crypt::encryptString(config('custom.appkeyCode'));
       
        if (Crypt::decryptString($encrypted_m) ===  Crypt::decryptString($encrypted_v)) {
            return $next($request);
            
        }else{
           
             return response()->json(['status' => false ,
                'message' => 'Unauthorized Accesss!'
            ]);
        }
    }
}
