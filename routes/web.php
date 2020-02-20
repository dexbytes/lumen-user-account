<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
//Check lumen version
$router->get('/', function () use ($router) {
    return $router->app->version();
});
//Email activation 
$router->get('/activation/{key}', 'UserController@activation');

//Check app mask key in middleware 
$router->group(['middleware' => 'appmask'], function () use ($router) {
	
	//User routes
	$router->group(['prefix' => 'user'], function () use ($router) {
		//User register
		$router->post('/register', 'UserController@doRegister');
		//Resend email
		$router->post('/resend-mail', 'UserController@resendMail');
		//User login
		$router->post('/login', 'UserController@login');
		//Forgot password
		$router->post('/forgot-password', 'UserController@forgotPassword');
		//Check otp
		$router->post('/check-otp', 'UserController@checkOtp');
		//Reset password
		$router->patch('/reset-password', 'UserController@resetPassword');
		//Auth token is required for using below routes 
		$router->group(['middleware' => 'auth'], function () use ($router) {
			//User logout
			$router->get('/logout', 'UserController@logout');
			//Check user account
			$router->get('/check-user-account', 'UserController@checkUserAccount');
			//Update user profile 
			$router->put('/update-profile', 'UserController@updateProfile');
            //Update user profile image
			$router->post('/update-profile-image', 'UserController@updateUserImage');
			//Remove profile image
			$router->post('/remove-profile-image', 'UserController@removeUserImage');
			//Get user profile
			$router->get('/get-profile', 'UserController@getProfile');
			//Change user password
			$router->patch('/change-password', 'UserController@changePassword');
		});		
	});
});	

