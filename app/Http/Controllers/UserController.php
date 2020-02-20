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
     * @param  Request  $request
     * @return Response 
     */
    public function doRegister(Request $request)
    {
        // Request validation
        $validator = Validator::make($request->all(),[                
            'full_name' => 'required',
            'mobile_number' => 'required|min:10|max:15',
            'email' => 'required|email',
            'password' => 'required|min:6|max:20',

        ],
        [   
            'full_name.required' => trans('auth.fNameRequired'), 
            'mobile_number.required' => trans('auth.mobileNumberRequired'),
            'mobile_number.min' => trans('auth.mobileNumberMin'),
            'mobile_number.max' => trans('auth.mobileNumberMax'), 
            'email.required' => trans('auth.emailRequired'),
            'email.email' => trans('auth.emailInvalid'),          
            'password.required' => trans('auth.passwordRequired'),
            'password.min' => trans('auth.PasswordMin'),
	        'password.max' => trans('auth.PasswordMax'),
		]);

   	   	// Validation function
        if ($validator->fails()) {
            //Send validation error response
            return $this->respondValidationErrorCustom($validator);
        }
  
        try{
            //Request variable
            $full_name = $request->full_name;
            $mobile_number = $request->mobile_number;
            $email = $request->email;
            $password = $request->password;
            
            //Check user is exist using email and mobile number
            $user= User::select('id', 'email', 'mobile_number')
            ->where('mobile_number', $mobile_number)
            ->orWhere('email', $email)
            ->first();

            //If user is exist
            if($user){
                //If email is exist
                if($user->email == $email){
                    //Send response with error
                    return $this->respondConflictError(trans('auth.emailExist'));
                }//If mobile number is exist
                elseif($user->mobile_number == $mobile_number){
                    //Send response with error
                    return $this->respondConflictError(trans('auth.mobileNumberExist'));
                }
            }
            else
            {
                //Generate email activation key
                $active_key =  $this->generateEmailActivationKey();
                
                //Insert user details
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
                
                //Email data
                $data = array('website_url'=>config('app.app_url'), 'full_name' =>  $full_name, 'email' => $email, 'mobile_number' => $mobile_number, 'active_link' => config('app.app_url').'activation/' . $active_key);

                try {
                    //Send email 
                    Mail::send('emails.register', $data, function ($message) use ($data) {
                        $message->to($data['email'])
                            ->subject('Please activate your account.');
                        
                    });
                    //Send success response
                    $message = trans('auth.verifyAccount');
                    return $this->respondSuccess($message);

                } catch (Exception $ex) {
                    //Mail error
                    $message = trans('auth.verifyPending');
                    return $this->respondSuccess($message);
                }
            }				
        }
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        } 	   
			           
		    
    }

    /**
     * Resend mail  
     *
     * @param  Request  $request
     * @return Response
     * 
     */
	public function resendMail(Request $request){

    	//Request validation
    	$validator = Validator::make($request->all(),[ 
				'email' => 'required|email',
	        ],
			[ 	
				'email.required'   => trans('auth.emailRequired'),          
                'email.email'        => trans('auth.emailInvalid'),
			]
		);
		
		//Send validation message 	
		if ($validator->fails()) {
			return $this->respondValidationErrorCustom($validator);
		}
   		try{
   			//Get user	
            $user = User::where('email', $request->email)->first();
            //Check if user is exist 
            if($user){
                //Generate email activation key
                $active_key =  $this->generateEmailActivationKey();
                $user->email_verification_code = $active_key;
                $user->save();
                //Email data
                $data = array('website_url'=>config('app.app_url'), 'full_name' =>  $user->full_name, 'email' => $user->email, 'mobile_number' => $user->mobile_number, 'active_link' => config('app.app_url').'activation/' . $active_key);

                try{
                    //Send email
                    Mail::send('emails.register', $data, function ($message) use ($data) {
                        $message->to($data['email'])
                            ->subject('Confirm Your Email');
                        
                    });
                    //Send success response
                    $message = trans('auth.verifyAccount');
                    return $this->respondSuccess($message);
                }
                catch(Exception $e){
                     //Email erorr response
                     $message = trans('auth.something_went_wrong');
                     return $this->respondConflictError($message);
                }
	        
            }
            //User not found response    
            $message = trans('auth.userNotFound');
            return $this->respondConflictError($message);        
   				
   		}
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }
		
    }

    /**
     * Generate activation key
     *
     */
    public function generateEmailActivationKey()
    {
        //Generate email activation key
        return hash_hmac('md5', str_random(5), config('app.key'));
    }

    /**
     * Login by auth 
     *
     * @param  $request $email $user
     * @return Response
     */
	public function loginByOAuth($request, $email, $user)
    {
        try {
            if($request->getHttpHost() == "localhost" || $request->getHttpHost() == "127.0.0.1"){
                //Request token url for local
                $auth_token_url = config('app.app_url');
            }else{
                //Request token url for server
                $auth_token_url = $request->getSchemeAndHttpHost();
            }
   			//Request token
   			$http = new \GuzzleHttp\Client;
                //Send request for generate auth token
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
            
            //Get auth token response  
            $responseBodyArr=json_decode((string) $response->getBody(), true);
            
            //Add user data in user_details variable
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
            
            //Send success response with message
            $msg = trans('auth.loginSuccess');
            return $this->respondOk($user_details, $msg);
		}
		catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        } 

    }

    /**
     * Add Device Token 
     *
     * @param  $user $request
     *
     */
    public function addDevicesToUser($user, $request)
	{
        //Remove if user device token is already exist
		Device::where('user_id', $user->id)->orWhere('device_token', $request['device_token'])->delete();
        //Add user device token
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
     * @param  Request  $request
     * @return Response
     */
 
    public function login(Request $request){
  
    	//Request validation
    	$validator = Validator::make($request->all(),[ 
                'email' => 'required|email',
                'password' => 'required|min:6|max:20',
            ],
            [ 	
                'email.required' => trans('auth.emailRequired'),          
                'email.email' => trans('auth.emailInvalid'),
                'password.required' => trans('auth.passwordRequired'),
                'password.min' => trans('auth.PasswordMin'),
                'password.max' => trans('auth.PasswordMax'),
            ]
        );

   	   	//Validation function
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }
       
        try {
   	     	//Get user
            $user = User::select('id', 'email' , 'password', 'is_email_verified', 'mobile_number', 'status')
            ->where('email', $request->email)
            ->first();
            
            //Check user
	    	if($user && app('hash')->check($request['password'], $user->password))
	   	    {	
                //Check user email is verified   
                if(!$user->is_email_verified){
                    //Login error 
                    $msg = trans('auth.loginErrorEmailNotVerified');
                    return $this->respondNotFound($msg);
                }
                //Check user status is active
                if(!$user->status){
                    // Login error 
                    $msg = trans('auth.loginErrorStatus');
                    return $this->respondNotFound($msg);
                }

                //Check device_token is exist in request
                if ($request->has('device_token')) {
                    $this->addDevicesToUser($user, $request);
                }

                //Login with auth token 
                return $this->loginByOAuth($request, $user->email, $user);
			
			}else{

				//Login error 
				$msg = trans('auth.loginError');
				return $this->respondNotFound($msg);
			}
   	    	

   	   	}
   	   	catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }    
    }

    

    /**
     * Get Profile function 
     *
     * @return \Illuminate\Http\Response
     */
    public function getProfile(){

        try{
            //Get user    
            $user = User::where('id', Auth::user()->id)->first();
            
            //Add user data in user_details variable 
            $user_details = [
                'user_id'=> (int)$user->id,
                'name'=> (string)$user->full_name,
                'user_mobile_no'=> (string)$user->mobile_number,
                'profile_image'=> $user->profile_image,
                'email'=> (string)$user->email,
                'is_active'=> $user->status,
                'country_phonecode'=> null
                
            ];

            //Send user details with success message
            $msg = trans('auth.profileDisplaySuccess');
            return $this->respondOk($user_details, $msg);
                
        }
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }
        
    }

    /**
     * Forgot Password function 
     *
     * @param  Request  $request
     * @return Response
     */
    public function forgotPassword(Request $request)
	{
		//Request validation
   	    $validator = Validator::make($request->all(),[                
                'email' => 'required|email',
            ],
            [   'email.required'   => trans('auth.emailRequired'),          
                'email.email'        => trans('auth.emailInvalid'),
            ]
        );

        //Check validation 
        if ($validator->fails()) {
            //Send error with validation message
            return $this->respondValidationErrorCustom($validator);
        }
        try{
            //Get user
            $user = User::select('id', 'password', 'email')->where('email', $request->email)->where('status', 1)->first();

            //Check user is exist
            if($user){
                //Create varification code
                $verificationCode = mt_rand(1000, 999999);
                $verificationCode = (string)$verificationCode;
                
                //Email data
                $data = array('email' => $user->email, 'verification_code'=> $verificationCode);
                
                try{
                   //Send email
                    Mail::send('emails.forgot-password', $data, function ($message) use ($data) {
                        $message->to($data['email'])
                            ->subject('Forgot password email');
                        
                    });

                }
                catch(Exception $e){
                    //Email erorr response
                    $message = trans('auth.something_went_wrong');
                    return $this->respondConflictError($message);
                }

                $user->reset_password_otp = $verificationCode;
                $user->reset_password_expires_at = Carbon::now()->addMinutes(2);
                $user->save();
                //Send response
                $message = trans('auth.forgotVerificationCodeSend');
                return $this->respondSuccess($message);
                
            }else{
                //User not found response    
                $message = trans('auth.userNotFound');
                return $this->respondConflictError($message);
            }
        }
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }    		
    }
    
    /**
     * Check Otp 
     *
     * @param  Request  $request
     * @return Response
     *
     */
	public function checkOtp(Request $request){

    	//Request validation
    	$validator = Validator::make($request->all(),[ 
				'email' => 'required|email',
	            'verification_code' => 'required',
			],
			[ 	
				'email.required'   => trans('auth.emailRequired'),          
                'email.email'        => trans('auth.emailInvalid'),
				'verification_code.required' => trans('auth.verificationCodeRequired')         
			]
		);
		
		//Check validation 	
		if ($validator->fails()) {
            // Send validation error
			return $this->respondValidationErrorCustom($validator);
		}
   		try{
   			//Check user verification code	
            $user = User::where('email', $request->email)
            ->where('reset_password_otp', $request->verification_code)
            ->where('reset_password_expires_at', '>=', Carbon::now())
            ->first();

            //If user is verified
            if($user){
                //Send success response
                $message = trans('auth.verificationCodeMatch');
                return $this->respondOk($request->verification_code,$message);
	        
            }
            //Send error response    
            $message = trans('auth.verificationCodeNotmatch', ['verification_code'=> $request->verification_code]);
            return $this->respondConflictError($message);        
   				
   		}
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }
		
    }

    	
	
    /**
     * Reset Password function 
     *
     * @param  Request  $request
     * @return Response
     */
	public function resetPassword(Request $request){

    	//Request validation
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
        
        //Check validation
		if ($validator->fails()) {
            //Send validation message 	
			return $this->respondValidationErrorCustom($validator);
        }
        
   		try{
   			//Get user	
            $user = User::where('email', $request->email)->where('reset_password_otp', $request->verification_code)->first();
            //Check if user is exist
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
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }
		
    }

	/**
     * Change Password function 
     *
     * @param  Request  $request
     * @return Response
     * 
     */
	public function changePassword(Request $request){

    	//Request validation
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
		
		//Check validation
		if ($validator->fails()) {
            //Send validation message 	
			return $this->respondValidationErrorCustom($validator);
		}
        
        try{
   			//Check current password 	
   			if(!Hash::check($request->current_password, Auth::user()->password)){	
	            // The passwords matches
	            $message =  trans('auth.currentPasswordMatch');
	            return response()->json(['status' => false , 
                    'message' => $message 
                ]);
            }
            //Check password  
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
	        //Send success response
	        $msg = trans('auth.passwordChangeSuccess');
            return $this->respondSuccess($msg);
   				
   		}
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }
		
    }

    

    /**
     * Update Profile function 
     *
     * @param  Request  $request
     * @return Response
     * 
     */
    public function updateProfile(Request $request){

        //Request validation
        $validator = Validator::make($request->all(),[                
            'full_name' => 'required|max:30',
        ],
        [   
            'full_name.required' => trans('auth.fullNameRequired'), 
            'full_name.max' => trans('auth.fullNameMax'), 
        ]);

        //Validation function
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }
        try{
            //Auth user
            $user = Auth::user();
                      
            //Update profile	
            $user->full_name = $request->full_name;
            $user->notification_allow = $request->notification_allow;
            $user->save();

            //Send success response 
            $msg = trans('auth.profileUpdatedSuccess');
            return $this->respondSuccess($msg);
                  
        }
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }
       
   }

    /**
     * Update user image function 
     *
     * @param  Request  $request
     * @return Response
     */
    public function updateUserImage(Request $request){

        //Request validation
        $validator = Validator::make($request->all(),[ 
                'user_image' => 'required',
            ],
            [   
                'user_image.required' => trans('auth.imageRequired'),
            ]
        );
        
        //Validation function
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }

        try{
            //Update profile image    
            $user = Auth::user();
            $filename = $user->id.'_'.time().str_random(5).'.jpg' ; 
            $user->profile_image = config('app.app_url').'/uploads/users/'.$filename;  
            $user->save();
            $file = $request->file('user_image');
            $path = config('app.app_url').'/uploads';
            //Check directory is exist
            if(!File::isDirectory($path)){
                //Make directory
                File::makeDirectory($path, 0777, true, true);
            }

            //Upload file
            Storage::disk('public')->put('users/'.$filename, file_get_contents($file), 'public');
            
            //Send success response 
            $msg = trans('auth.profileUpdatedSuccess');
            return $this->respondSuccess($msg);
                
        }
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }    
        
    }

    /**
     * Remove user image 
     *
     * @param  Request  $request
     * @return Response
     * 
     */
    public function removeUserImage(Request $request){
        
        //Request validation
        $validator = Validator::make($request->all(),[ 
                'image_name' => 'required',
            ],
            [   
                'image_name.required' => trans('auth.imageNameRequired'),
            ]
        );
        
        //Send validation message  
        if ($validator->fails()) {
            return $this->respondValidationErrorCustom($validator);
        }

        try{
            //Image path
            $image_path = config('app.api_directory').'uploads/users/'.$request->image_name;  
            //Check image is exist
            if(file_exists($image_path)) {
               //Unlink Image
                unlink($image_path);
            }
            //Auth user
            $user = Auth::user();
            $user->profile_image = Null;  
            $user->save();
            
            //Send success response 
            $msg = trans('auth.profileUpdatedSuccess');
            return $this->respondSuccess($msg);
                
        }
        catch(Exception $e){
            report($e);
            //Internal error response
            return $this->respondInternalError('Internal Server Error', $e);
        }   
        
    }

    /**
     * Logout 
     *
     * @param  Request  $request
     * @return Response
     * 
     */
    public function logout(Request $request){

   	    try {
            //Auth user   
            $user =  Auth::user();
            //Remove device token    
	   		Device::where('user_id',$user->id)->delete(); 
	   		$accessToken = Auth::user()->token();
            //Refresh auth user token 
	        $refreshToken = DB::
	            table('oauth_refresh_tokens')
	            ->where('access_token_id', $accessToken->id)
	            ->update([
	                'revoked' => true
	            ]);


	        $accessToken->revoke();
	   	
            //Send success response 
            $message = trans('auth.logOutSuccess');        
            return $this->respondSuccess($message);   

   	    }
        catch(Exception $e){
            report($e);
            //Internal error response
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
        //Get user using email key
        $auth_user = User::where('email_verification_code', $key)->first();
        //Check user is exist
        if ($auth_user) {
            //Check user email is verified
            if ($auth_user->is_email_verified == 1) {
                //Send email verified response
            	return view('emails.emailverification');
            }
            $auth_user->is_email_verified=1;
            $auth_user->save();
            //Send email verified response
            return view('emails.emailverification');
            
        } else {
            //Send email verification error response
        	return view('emails.emailverificationerror');
        }
    }
   
}
