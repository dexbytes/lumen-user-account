<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; 
use App\Http\Controllers\ApiController;
use Exception;
use Validator;
use Auth;
use DB;
use Carbon\Carbon;
use App\Models\AppMask;

/**
* Create a new Commmon controller.
*
* @return void
*/
class CommonController extends ApiController
{
     /**
     * Create a new common instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Add phone details function
     *
     * @param string $key
     * @return redirect desired page
     */
    public function phoneDetails(Request $request)
    {
        try{
           
            AppMask::firstOrCreate([
                        'unique_id'=>$request->header('uniqueid')
                    ],
                    [
                        'build_id'=>$request->header('buildid'),
                        'mac_address' => $request->header('macaddress')
                    ]);
            //send success response 
            $message = trans('auth.addPhoneDetails');        
            return $this->respondSuccess($message); 
        }
        catch(Exception $e){
     
            report($e);
            return $this->respondInternalError('Internal Server Error', $e);
        } 
    }
}