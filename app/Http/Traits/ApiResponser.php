<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response as Res;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;


trait ApiResponser
{
 
	/**
	     * @var int
	     */
	protected $statusCode = Res::HTTP_OK;
	protected $perPage = 15;

	protected function successResponse($data, $code, $message='', $httpCode=200)
	{
		return response()->json(['status'=>1,'message'=>$message, 'data' => $data, 'code' => $code],
		 $httpCode, [], JSON_NUMERIC_CHECK);
	}

	protected function errorResponse($message, $code, $httpCode=404)
	{
		return response()->json(['status'=>0,'message'=>$message, 'code' => $code], $httpCode);
	}
    protected function showAllTransform(Collection $collection,  $transformer, $code = 200, $message='')
	{
		if ($collection->isEmpty()) {
			return $this->successResponse($collection, $code, $message);
		}

		//$collection = $this->sortData($collection, $transformer);

		//$collection = $this->paginate($collection);
		
		$collection = $this->transformData($collection, $transformer);
		//$collection = $this->cacheResponse($collection);	
		
		return $this->successResponse($collection, $code, $message);
	}	
	protected function metaArray($collection)
	{
		$collection->map(function($item, $key){

		});	
	}
	protected function showAll(Collection $collection, $code = 200, $message='')
	{
		if ($collection->isEmpty()) {
			return $this->successResponse( $collection, $code, $message);
		}

		$transformer = $collection->first()->transformer;
		//$collection = $this->filterData($collection, $transformer);

		$collection = $this->sortData($collection, $transformer);

		//$meta = $this->metaArray($collection);

		$collection = $this->paginate($collection);


		$collection = $this->transformData($collection, $transformer);
		//$collection = $this->cacheResponse($collection);	


		
		return $this->successResponse($collection, $code, $message);
	}

	protected function showOne(Model $instance, $code = 200, $message='')
	{
		$transformer = $instance->transformer;

		//$transformer::$isList=false;

		$instance = $this->transformData($instance, $transformer);
		$instance = $instance['data'];
		return $this->successResponse($instance, $code, $message);
	}

	protected function getOne(Model $instance)
	{
		$transformer = $instance->transformer;

		//$transformer::$isList=false;

		$instance = $this->transformData($instance, $transformer);

		return $instance;
	}

	protected function showMessage($message, $code = 200)
	{
		return $this->successResponse( $message, $code, $message);
	}

	protected function filterData(Collection $collection, $transformer)
	{
		foreach (app('request')->query() as $query => $value) {
			$attribute = $transformer::originalAttribute($query);
			if (isset($attribute, $value)) {

				$collection = $collection->where($attribute, $value);
			}
		}

		return $collection;
	}

	protected function sortData(Collection $collection, $transformer)
	{
		if (app('request')->has('sort_by')) {
			$attribute = $transformer::originalAttribute(app('request')->sort_by);

			if (app('request')->has('order') && app('request')->order=='desc')
				$collection = $collection->sortByDesc->{$attribute};
			else
				$collection = $collection->sortBy->{$attribute};
		}

		return $collection;
	}

	protected function paginate(Collection $collection, $perPage = 15)
	{
		$rules = [
			'per_page' => 'integer|min:2|max:100',
		];
		Validator::validate(app('request')->all(), $rules);
		$page = LengthAwarePaginator::resolveCurrentPage();
		
		if (app('request')->has('per_page')) {
			$perPage = (int) app('request')->per_page;
		}
		$results = $collection
		->slice(($page - 1) * $perPage, $perPage)
		->values()
		;
		
		if(!$results->count())
		{
			$results = $collection->slice((0) * $perPage, $perPage)->values();
			
		}

		$paginated = new LengthAwarePaginator($results, $collection->count(), $perPage, $page, [
			'path' => LengthAwarePaginator::resolveCurrentPath(),
		]);
		$paginated->appends(app('request')->all());

		return $paginated;
	}

	protected function paginateCollection(Collection $collection, $perPage = 15)
	{
		$rules = [
			'per_page' => 'integer|min:2|max:100',
		];
		Validator::validate(app('request')->all(), $rules);
		$page = LengthAwarePaginator::resolveCurrentPage();
		
		if (app('request')->has('per_page')) {
			$perPage = (int) app('request')->per_page;
		}
		$results = $collection->slice(($page - 1) * $perPage, $perPage)->values();
		
		if(!$results->count())
		{
			$results = $collection->slice((0) * $perPage, $perPage)->values();
			
		}

		/*$paginated = new LengthAwarePaginator($results, $collection->count(), $perPage, $page, [
			'path' => LengthAwarePaginator::resolveCurrentPath(),
		]);
		$paginated->appends(app('request')->all());*/

		return $results;
	}


	protected function transformData($data, $transformer)
	{
		$transformation = fractal($data, new $transformer);

		return $transformation->toArray();
	}

	protected function cacheResponse($data)
	{
		$url = app('request')->url();
		$queryParams = app('request')->query();
		ksort($queryParams);
		$queryString = http_build_query($queryParams);
		$fullUrl = "{$url}?{$queryString}";

		return Cache::remember($fullUrl, 30/60, function() use($data) {
			return $data;
		});
	}



		public function setPerPage()
		{
		  if (app('request')->has('per_page')) {
			$this->perPage = (int) app('request')->per_page;
		  }

		  return $this;

		}

		public function getPerPage()
		{
		   return $this->setPerPage()->perPage;

		}


	    /**
	     * @return mixed
	     */
	    public function getStatusCode()
	    {
	        return $this->statusCode;
	    }
	    /**
	     * @param $message
	     * @return json response
	     */
	    public function setStatusCode($statusCode)
	    {
	        $this->statusCode = $statusCode;
	        return $this;
	    }

   
	  public function respondCreated($message, $data=null){

	  	$this->setStatusCode(Res::HTTP_CREATED);

        return $this->respond([
        	//'success' => true,
            'status' => 'success',
            'status_code' => Res::HTTP_CREATED,
            'message' => $message,
            'data' => $data
        ]);
    }


   protected function respondOK($data, $message){

        return $this->respond([
        	'status' => true,
            'message' => $message,
            'data' => $data
        ]);
	}
	
	protected function respondSuccess($message){

        return $this->respond([
        	'status' => true,
            'message' => $message,
        ]);
    }


    protected function respondSingle($data, $message){
        $transformer = $data->transformer;

    	$data = $this->transformData($data, $transformer);

    	$data = $data['data'];

        return $this->respond([
        	'status' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * @param Paginator $paginate
     * @param $data
     * @return mixed
     */
    protected function respondWithPagination($data, $message){

    	$transformer = $data->first()->transformer;

    	$data = $this->transformData($data, $transformer);

        return $this->respond([
        	'success' => true,
            'status' => 'success',
            'status_code' => Res::HTTP_OK,
            'message' => $message,
            'data' => $data
        ]);
    }

    public function respondNotFound($message = 'Not Found!'){
    	//$this->setStatusCode(Res::HTTP_NOT_FOUND);

        return $this->respond([
        	'status' => false,
            'message' => $message,
            //'data' => [],
        ]);
    }

    public function respondInternalError($message, $error){

    	$this->setStatusCode(Res::HTTP_INTERNAL_SERVER_ERROR);

        return $this->respond([
        	'success' => false,
            'status' => 'error',
            'status_code' => Res::HTTP_INTERNAL_SERVER_ERROR,
            'message' => $message,
            'error'=> [
                    "errors" => [
                        "file" => $error->getFile(),
                        "line" => $error->getLine(),
                        "exception" => (new \ReflectionClass($error))->getShortName(),
                    ],
                    "code" => $error->getCode(),
                    "message" => $error->getMessage()
                ]
        ]);
    }

    public function respondValidationError($message, $errors, $messageBag){

    	$this->setStatusCode(Res::HTTP_UNPROCESSABLE_ENTITY);

		$newErrors = [];
    	foreach($errors->messages() as $field=>$error)
    	{
    		
    		$newError =[];

    		foreach($error as $errorMessage)
    		{
    			
    			$newMessage['code']=$errorMessage;
    			$newMessage['message']=$messageBag[$field.'.'.explode('.',$errorMessage)[1]];

    			$newError[]=$newMessage;
    		}

    		$newErrors[$field]=$newError;
    	}

        return $this->respond([
        	'status' => false,
            'message' => $message,
            'data' => $newErrors
        ]);
    }

    public function respond($data, $headers = []){
        return response()->json($data, $this->getStatusCode(), $headers);
    }

     public function respondUnauthrizedError($message){

    	$this->setStatusCode(Res::HTTP_UNAUTHORIZED);

        return $this->respond([
        	'success' => false,
            'status' => 'error',
            'status_code' => Res::HTTP_UNAUTHORIZED,
            'message' => $message,
        ]);
    }

    public function respondForbiddenError($message){

    	$this->setStatusCode(Res::HTTP_FORBIDDEN);

        return $this->respond([
        	'status' => false,
            'message' => $message,
        ]);
    }

    public function respondConflictError($message){

    	//$this->setStatusCode(Res::HTTP_CONFLICT);

        return $this->respond([
        	'status' => false,
            'message' => $message,
        ]);
	}
	
	public function respondValidationErrorCustom($validator){

		//$this->setStatusCode(Res::HTTP_CONFLICT);
		$msg = array(trans('auth.error'));
		$messages = $validator->messages();
		foreach ($messages->all() as $message)
		{
				$msg[] = $message; 
		}
		$msg = join(',', $msg);

        return $this->respond([
        	'status' => false,
            'message' => $msg,
        ]);
    }

    public function getQueryLog()
    {
    	    $queries = DB::getQueryLog();
			if (!empty($queries)) {
			    foreach ($queries as &$query) {
			        $query['full_query'] = vsprintf(str_replace('?', '%s', $query['query']), $query['bindings']);
			    }
			}
			dd($queries);
    }
}