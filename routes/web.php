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

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/activation/{key}', 'UserController@activation');

$router->group(['middleware' => 'appmask'], function () use ($router) {
	
	$router->post('/phone-details', 'CommonController@phoneDetails');
	
	//User routes
	$router->group(['prefix' => 'user'], function () use ($router) {
		$router->post('/register', 'UserController@doRegister');
		$router->post('/resend-mail', 'UserController@resendMail');
		$router->post('/login', 'UserController@login');
		$router->post('/forgot-password', 'UserController@forgotPassword');
		$router->post('/check-otp', 'UserController@checkOtp');
		$router->post('/reset-password', 'UserController@resetPassword');
		//Auth token is required for using below routes 
		$router->group(['middleware' => 'auth'], function () use ($router) {
			$router->get('/logout', 'UserController@logout');
			$router->get('/check-user-account', 'UserController@checkUserAccount');
			$router->post('/update-profile', 'UserController@updateProfile');
			$router->post('/update-profile-image', 'UserController@updateUserImage');
			$router->post('/remove-profile-image', 'UserController@removeUserImage');
			$router->get('/get-profile', 'UserController@getProfile');
			$router->get('/get-user-details/{id}', 'UserController@getUserDetails');
			$router->post('/change-password', 'UserController@changePassword');
		});		
	});
});	

