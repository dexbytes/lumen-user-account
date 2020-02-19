<?php

namespace App\Http\Controllers;
 
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; 
use Carbon\Carbon;
use App\User;
use App\Models\Device;
use Validator;
use Auth;
use DB;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;


class UserController extends ApiController
{
    
    /**
     * Override default register method from RegistersUsers trait
     *
     * @param array $request
     * @return redirect to $redirectTo
     */
    public function doRegister(Request $request)
    {
        // request validation
        $validator = Validator::make($request->all(),[                
            'full_name' => 'required',
            'mobile_number' => 'required|min:10|max:15',
            'email' => 'required',
            'password' => 'required',

        ],
        [   
            'full_name.required' => trans('auth.fNameRequired'), 
            'mobile_number.required' => trans('auth.mobileNumberRequired'),
            'mobile_number.min' => trans('auth.mobileNumberMin'),
            'mobile_number.max' => trans('auth.mobileNumberMax'), 
        	'email.required' => trans('auth.emailRequired'),          
            'password.required'      => trans('auth.passwordRequired'),
		]);

   	   	// validation function
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }
  
        try{
            //request variable
            $full_name = $request->full_name;
            $mobile_number = $request->mobile_number;
            $email = $request->email;
            $password = $request->password;
            
            //get user
            $user= User::select('id', 'email', 'mobile_number')
            ->where('mobile_number', $mobile_number)
            ->orWhere('email', $email)
            ->first();

            //check user
            if($user){
                if($user->email == $email){
                    return $this->respondConflictError(trans('auth.emailExist'));
                }elseif($user->mobile_number == $mobile_number){
                    return $this->respondConflictError(trans('auth.mobileNumberExist'));
                }
            }
            else
            {
                $active_key =  $this->getToken();

                $user = new User;
                $user->full_name = $full_name;
                $user->email = $email;
                $user->mobile_number = $mobile_number;
                $user->password = bcrypt($password);
                $user->email_verification_code = $active_key;
                $user->status = 1;
                $user->save();
                $role = 2;
                $user->roles()->sync($role);
                
                $data = array('website_url'=>config('app.app_url'), 'full_name' =>  $full_name, 'email' => $email, 'mobile_number' => $mobile_number, 'active_link' => config('app.app_url').'activation/' . $active_key);

                Mail::send('emails.register', $data, function ($message) use ($data) {
                    $message->to($data['email'])
                        ->subject('Confirm Your Email');
                    
                });

                $message = trans('auth.verifyAccount');
                return $this->respondSuccess($message);
            }				
                
        }
        catch(Exception $e){
            report($e);

            return $this->respondInternalError('Internal Server Error', $e);
        } 	   
			           
		    
    }

    /**
     * Change Password function 
     *
     * @return \Illuminate\Http\Response
     */
	public function resendMail(Request $request){

    	// request validation
    	$validator = Validator::make($request->all(),[ 
				'email' => 'required|email',
	        ],
			[ 	
				'email.required'   => trans('auth.emailRequired'),          
                'email.email'        => trans('auth.emailInvalid'),
			]
		);
		
		// send validation message 	
		if ($validator->fails()) {
			return $this->respondValidationErrorCustom($validator);
		}
   		try{
   				
            $user = User::where('email', $request->email)->first();
            if($user){
                $active_key =  $this->getToken();
                $user->email_verification_code = $active_key;
                $user->save();
                $data = array('website_url'=>config('app.app_url'), 'full_name' =>  $user->full_name, 'email' => $user->email, 'mobile_number' => $user->mobile_number, 'active_link' => config('app.app_url').'activation/' . $active_key);

                Mail::send('emails.register', $data, function ($message) use ($data) {
                    $message->to($data['email'])
                        ->subject('Confirm Your Email');
                    
                });

                $message = trans('auth.verifyAccount');
                return $this->respondSuccess($message);
	        
            }
                
            $message = trans('auth.userNotFound');

            return $this->respondConflictError($message);        
   				
   		}
	    catch(Exception $e){
	   		return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
	   	}
		
    }

     /**
     * Generate function 
     *
     * @return \Illuminate\Http\Response
     */
    public function getToken()
    {
        return hash_hmac('md5', str_random(5), config('app.key'));
    }

    /**
     * Login function 
     *
     * @return \Illuminate\Http\Response
     */
	public function loginByOAuth($request, $email, $user)
    {

   		try {
   			if($request->getSchemeAndHttpHost() == "http://localhost"){
                $auth_token_url = config('app.app_url');
            }else{
                $auth_token_url = $request->getSchemeAndHttpHost();
            }
   			// request token
   			$http = new \GuzzleHttp\Client;

				$response = $http->post($auth_token_url.'/api/v1/oauth/token', [
			    'form_params' => [
			        'grant_type' => 'password',
			        'client_id' => config('app.password_client_id'),
			        'client_secret' => config('app.password_client_secret'),
			        'username' => $email,
			        'password' =>  $request['password'],
			        'scope' => '*',
			    ],
			]);
            
            $responseBodyArr=json_decode((string) $response->getBody(), true);
            
            $user_details = [
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'status' => $user->status,
                'is_email_verified' => $user->is_email_verified,
                'mobile_number' => $user->mobile_number,               
                'token_type'=> $responseBodyArr['token_type'],
                'token'=> $responseBodyArr['access_token']
            ];
            
            $msg = trans('auth.loginSuccess');
            //send response 
            return response()->json(['status' => true , 
                'message' =>    $msg,
                'data'  => $user_details       
            ]);
		}
		catch(Exception $e){
   	   		return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
   	   	} 

    }

    /**
     * Add Device Token 
     *
     * @return \Illuminate\Http\Response
     */
    public function addDevicesToUser($user, $request)
	{
		Device::where('user_id', $user->id)->orWhere('device_token', $request['device_token'])->delete();
		$user->devices()
	  	      ->firstOrCreate(['user_id'=>$user->id,'device_token'=>$request['device_token']
							  ],
				   	   			['device_type'=>$request['device_type'],
				   	   			'device_version'=>$request['device_version'],
				   	   			'device_os_version'=>$request['device_os_version']
		   	   			        ]
				   	   		 );
    }
    

    /**
     * Login function 
     *
     * @return \Illuminate\Http\Response
     */
 
    public function login(Request $request){
  
    	// request validation
   	    $validator = Validator::make($request->all(),[                
            'email' => 'required',
            'password' => 'required',
        ],
        [   
        	'email.required' => trans('auth.emailRequired'),          
            'password.required'      => trans('auth.passwordRequired'),
		]);

   	   	// validation function
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }
       
        try {
   	     	
            $user = User::select('id', 'password', 'email' , 'is_email_verified', 'mobile_number', 'status')
            ->where('email', $request->email)
            ->first();
            
            //check user status
            if($user){
                
                if(!$user->is_email_verified){
                    // Login error 
                    $msg = trans('auth.loginErrorEmailNotVerified');
                    return $this->respondNotFound($msg);
                }
                if(!$user->status){
                    // Login error 
                    $msg = trans('auth.loginErrorStatus');
                    return $this->respondNotFound($msg);
                }
            
            }
            
            //check user
	    	if($user && app('hash')->check($request['password'], $user->password))
	   	    {	
                // Login device
                if ($request->has('device_token')) {
                    $this->addDevicesToUser($user, $request);
                }

                // Login function
                return $this->loginByOAuth($request, $user->email, $user);
			
			}else{

				// Login error 
				$msg = trans('auth.loginError');
				return $this->respondNotFound($msg);
			}
   	    	

   	   	}
   	   	catch(Exception $e){
   	   		// Internal server error
   	   		return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
   	   	}    
    }

    

    /**
     * Get Profile function 
     *
     * @return \Illuminate\Http\Response
     */
    public function getProfile(){

        try{
            //get user    
            $user = User::where('id', Auth::user()->id)->first();

            $user_details = [
                'user_id'=> (int)$user->id,
                'name'=> (string)$user->full_name,
                'user_mobile_no'=> (string)$user->mobile_number,
                'profile_image'=> $user->profile_image,
                'email'=> (string)$user->email,
                'is_active'=> $user->status,
                'country_phonecode'=> null
                
            ];
            //send user details
            $msg = trans('auth.profileDisplaySuccess');
            return $this->respondOk($user_details, $msg);
                
        }
        catch(Exception $e){
            return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
        }
        
    }

    /**
     * Get User Profile function 
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserDetails($id){

        try{
            
            //Get user    
            $user = User::where('id', $id)->first();
            
            if($user->profile_image){
                $profile_image = config('app.app_url').'/uploads/users/'.$user->profile_image;
            }else{
                $profile_image = config('app.app_url').'/uploads/images/male-placeholder-image.png';
            }

            $qr_code_image = '';
            if($user->my_referring_code){
                $qr_code_image = config('app.app_url').'/uploads/qrcode/'.base64_encode($user->my_referring_code).'.png';
            }

            $user_details = [
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                //'my_referring_code' => $user->my_referring_code,
                'profile_image'=> $profile_image,
                //'user_qr_code' => $qr_code_image,
                'email'=> (string)$user->email,
                //'notification_allow'=> $user->notification_allow,
            ];

            //send user details
            $msg = trans('auth.profileDisplaySuccess');
            return $this->respondOk($user_details, $msg);
                
        }
        catch(Exception $e){
            return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
        }
        
    }

    /**
     * Forgot Password function 
     *
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(Request $request)
	{
		// Request validation
   	    $validator = Validator::make($request->all(),[                
                'email' => 'required|email',
            ],
            [   'email.required'   => trans('auth.emailRequired'),          
                'email.email'        => trans('auth.emailInvalid'),
            ]
        );

   	    // Send validation message
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }
        //Get user
        $user = User::select('id', 'password', 'email')->where('email', $request->email)->where('status', 1)->first();

   		// check user
		if($user){
            // create varification code
            $verificationCode = mt_rand(1000, 999999);
		    $verificationCode = (string)$verificationCode;
            
            $data = array('email' => $user->email, 'verification_code'=> $verificationCode);
            
            Mail::send('emails.forgot-password', $data, function ($message) use ($data) {
                $message->to($data['email'])
                    ->subject('Forgot password email');
                
            });

            $user->reset_password_otp = $verificationCode;
            $user->reset_password_expires_at = Carbon::now()->addMinutes(2);
            $user->save();
            //Send response
            $message = trans('auth.forgotVerificationCodeSend');
            return $this->respondSuccess($message);
		    
	   }else{
	   		return response()->json(['status' => false , 
                'message' =>  trans('auth.userNotFound')       
            ]);
	   }		
    }
    
    /**
     * Check Otp 
     *
     * @return \Illuminate\Http\Response
     */
	public function checkOtp(Request $request){

    	// request validation
    	$validator = Validator::make($request->all(),[ 
				'email' => 'required|email',
	            //'password' => 'required|min:6|max:20',
	            'verification_code' => 'required',
			],
			[ 	
				'email.required'   => trans('auth.emailRequired'),          
                'email.email'        => trans('auth.emailInvalid'),
				// 'password.required' => trans('auth.passwordRequired'),
				// 'password.min' => trans('auth.PasswordMin'),
				// 'password.max' => trans('auth.PasswordMax'),
				'verification_code.required' => trans('auth.verificationCodeRequired')         
			]
		);
		
		// send validation message 	
		if ($validator->fails()) {
			return $this->respondValidationErrorCustom($validator);
		}
   		try{
   				
            $user = User::where('email', $request->email)
            ->where('reset_password_otp', $request->verification_code)
            ->where('reset_password_expires_at', '>=', Carbon::now())
            ->first();
            if($user){
                $message = trans('auth.verificationCodeMatch');
                return $this->respondOk($request->verification_code,$message);
	        
            }
                
            $message = trans('auth.verificationCodeNotmatch', ['verification_code'=> $request->verification_code]);

            return $this->respondConflictError($message);        
   				
   		}
	    catch(Exception $e){
	   		return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
	   	}
		
    }

    	
	
    /**
     * Change Password function 
     *
     * @return \Illuminate\Http\Response
     */
	public function resetPassword(Request $request){

    	// request validation
    	$validator = Validator::make($request->all(),[ 
				'email' => 'required|email',
	            'password' => 'required|min:6|max:20',
	            'verification_code' => 'required',
			],
			[ 	
				'email.required'   => trans('auth.emailRequired'),          
                'email.email'        => trans('auth.emailInvalid'),
				'password.required' => trans('auth.passwordRequired'),
				'password.min' => trans('auth.PasswordMin'),
				'password.max' => trans('auth.PasswordMax'),
				'verification_code.required' => trans('auth.verificationCodeRequired')         
			]
		);
		
		// send validation message 	
		if ($validator->fails()) {
			return $this->respondValidationErrorCustom($validator);
		}
   		try{
   			//Get user	
            $user = User::where('email', $request->email)->where('reset_password_otp', $request->verification_code)->first();
            //Check user
            if($user){
                $user->password = bcrypt($request->password);
                $user->save();
                
                //Send success response
                $message = trans('auth.updatePassword');
                return $this->respondSuccess($message);
	        
            }
            //Send response with error message               
            $message = trans('auth.verificationCodeNotmatch', ['verification_code'=> $request->verification_code]);
            return $this->respondConflictError($message);        
   				
   		}
	    catch(Exception $e){
	   		return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
	   	}
		
    }

	/**
     * Change Password function 
     *
     * @return \Illuminate\Http\Response
     */
	public function changePassword(Request $request){

    	// request validation
    	$validator = Validator::make($request->all(),[ 
				'current_password' => 'required',
	            'password' => 'required|min:6|max:20',
	        ],
			[ 	
				'current_password.required' => trans('auth.currentPasswordRequired'),
				'password.required' => trans('auth.passwordRequired'),
				'password.min' => trans('auth.PasswordMin'),
				'password.max' => trans('auth.PasswordMax'),
			]
		);
		
		// send validation message 	
		if ($validator->fails()) {
			$msg = array("Error:");
			$messages = $validator->messages();
			foreach ($messages->all() as $message)
			{
				$msg[] = $message; 
			}
			$msg = join(',', $msg);
			return response()->json(['status' => false , 
				'message' => $msg 
			]);
        }
        
        try{
   				
   			if(!Hash::check($request->current_password, Auth::user()->password)){	
	            // The passwords matches
	            $message =  trans('auth.currentPasswordMatch');
	            return response()->json(['status' => false , 
                    'message' => $message 
                ]);
            }

            if(Hash::check($request->password, Auth::user()->password)){	
	            // The passwords matches
	            $message =  trans('auth.passwordCannotBeSame');
	            return response()->json(['status' => false , 
                    'message' => $message 
                ]);
            }
            
	        $user = Auth::user();
	        $user->password = bcrypt($request->password);
	        $user->save();
	        
	        $msg = trans('auth.passwordChangeSuccess');
            
            return response()->json(['status' => true , 
                'message' => $msg 
            ]);
   				
   		}
	    catch(Exception $e){
	   		return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
	   	}
		
    }

    

    /**
     * Update Profile function 
     *
     * @return \Illuminate\Http\Response
     */
    public function updateProfile(Request $request){

        // request validation
        $validator = Validator::make($request->all(),[                
           'full_name' => 'required|max:30',
       ],
       [   
           'full_name.required' => trans('auth.fullNameRequired'), 
           'full_name.max' => trans('auth.fullNameMax'), 
       ]);

                // validation function
       if ($validator->fails()) {
           return $this->respondValidationErrorCustom($validator);
       }
          try{

           $user = Auth::user();
                      
             //update profile	
           $user->full_name = $request->full_name;
           $user->notification_allow = $request->notification_allow;
           $user->save();

           //send success response 
           $msg = trans('auth.profileUpdatedSuccess');
           return $this->respondSuccess($msg);
                  
          }
       catch(Exception $e){
              return response()->json(['status' => false , 
               'message' => trans('auth.InternalServerError' . $e->getMessage())          
           ]);
          }
       
   }

    /**
     * Update user image function 
     *
     * @return \Illuminate\Http\Response
     */
    public function updateUserImage(Request $request){

        // request validation
        $validator = Validator::make($request->all(),[ 
                'user_image' => 'required',
            ],
            [   
                'user_image.required' => trans('auth.imageRequired'),
            ]
        );
        
        // send validation message  
        if ($validator->fails()) {
            $msg = array("Error:");
            $messages = $validator->messages();
            foreach ($messages->all() as $message)
            {
                $msg[] = $message; 
            }
            $msg = join(',', $msg);
            return response()->json(['status' => false , 
                'message' => $msg 
            ]);
        }

        try{
            //update profile image    
            $user = Auth::user();
            $filename = $user->id.'_'.time().str_random(5).'.jpg' ; 
            $user->profile_image = config('app.app_url').'/uploads/users/'.$filename;  
            $user->save();
            $file = $request->file('user_image');
            $path = config('app.app_url').'/uploads';
            if(!File::isDirectory($path)){
                File::makeDirectory($path, 0777, true, true);
        
            }
            Storage::disk('public')->put('users/'.$filename, file_get_contents($file), 'public');
            
            //send success response 
            $msg = trans('auth.profileUpdatedSuccess');
            return $this->respondSuccess($msg);
                
        }
        catch(Exception $e){
            return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
        }    
        
    }

    /**
     * Remove user image function 
     *
     * @return \Illuminate\Http\Response
     */
    public function removeUserImage(Request $request){
        
        // request validation
        $validator = Validator::make($request->all(),[ 
                'image_name' => 'required',
            ],
            [   
                'image_name.required' => trans('auth.imageNameRequired'),
            ]
        );
        
        // send validation message  
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }

        try{

            $image_path = config('app.api_directory').'uploads/users/'.$request->image_name;  
            
            if(file_exists($image_path)) {
                unlink($image_path);
            }

            $user = Auth::user();
            $user->profile_image = Null;  
            $user->save();
            
            //send success response 
            $msg = trans('auth.profileUpdatedSuccess');
            return $this->respondSuccess($msg);
                
        }
        catch(Exception $e){
            return response()->json(['status' => false , 
                'message' => trans('auth.InternalServerError' . $e->getMessage())          
            ]);
        }    
        
    }

    /**
     * Logout function 
     *
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request){

   	    try {
   	 		$user =  Auth::user();
	   		Device::where('user_id',$user->id)->delete(); 
	   		$accessToken = Auth::user()->token();

	        $refreshToken = DB::
	            table('oauth_refresh_tokens')
	            ->where('access_token_id', $accessToken->id)
	            ->update([
	                'revoked' => true
	            ]);


	        $accessToken->revoke();
	   	
            //send success response 
            $message = trans('auth.logOutSuccess');        
            return $this->respondSuccess($message);   

   	    }
        catch(Exception $e){
	   		report($e);

	   		return $this->respondInternalError('Internal Server Error', $e);
	   	} 	
    }

    /**
     * Activate the user account
     *
     * @param string $key
     * @return redirect desired page
     */
    public function activation($key)
    {
        
        $auth_user = User::where('email_verification_code', $key)->first();
        if ($auth_user) {
            if ($auth_user->is_email_verified == 1) {
            	return view('emails.emailverification');
            }
            $auth_user->is_email_verified=1;
            $auth_user->save();
            
            return view('emails.emailverification');
            
        } else {
        	return view('emails.emailverificationerror');
        }
    }
   
}
