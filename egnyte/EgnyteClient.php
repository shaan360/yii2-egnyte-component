<?php
namespace common\components\egnyte;


use common\components\egnyte\lib\Curl;
use common\components\egnyte\lib\CurlResponse;

/**
 * Simple Class to manage Egnyte uploads with the Egnyte public API (https://developer.egnyte.com)
 */
Class EgnyteClient {
	
	protected $oauthToken;
	protected $domain;
	protected $baseUrl;
	protected $curl;

	/**
	 * Instantiates the Egnyte Client
	 * @param string $domain     Egnyte domain name, e.g. mycompany
	 * @param string $oauthToken oAuth token associated with the user for whom the actions will be performed
	 */
	public function __construct($domain, $oauthToken) {
		if(!extension_loaded('curl')) {
			throw new Exception('EgnyteClient requires the PHP Curl extension to be enabled');
		}

		$this->domain = $domain;
		$this->oauthToken = $oauthToken;
		$this->baseUrl = 'https://' . $domain . '.egnyte.com/pubapi/v1';

		$this->curl = new Curl;
		
		// set an HTTP header with the oAuth token
		$this->curl->headers['Authorization'] = "Bearer $oauthToken";

		// real deploymnets should do SSL verification, but for simplicity this is turned off
		// since PHP's curl extension (at least on Windows) does not have certificates setup by default
		$this->curl->options['CURLOPT_SSL_VERIFYPEER'] = false;
		
	}

	/**
	 * Upload a file to Egnyte
	 * @param  string $cloudPath    Folder path where the file should be uploaded, including trailing slash
	 * @param  string $fileName     File name for the file
	 * @param  string $fileContents Binary contents of the file
	 * @return EgnyteResponse       Response object
	 */
	public function uploadFile($cloudPath, $fileName, $fileContents) {
		// path names are in the URL, so they need to be encoded
		$path = self::encodePath($cloudPath . $fileName);

		// set a content type for the upload: application/octet-stream can safely be used for all file types since we are sending binary data
		$this->curl->headers['Content-Type'] = "application/octet-stream";

		// send the api request and return the HTTP response from the server
		$response = $this->post("/fs-content" . $path, $fileContents, array(
			400 => 'Bad request - missing parameters, file filtered out (e.g. .tmp file) or file is too large (>100 MB)',
			401 => 'User not authorized',
			403 => 'Not enough permissions / forbidden file upload location ( e.g. /, /Shared, /Private etc.)'
		));
		return $response;
	}

	/**
	 * Get the metadata for a file
	 * @param  string $path The full path to a file in the cloud
	 * @return EgnyteResponse       Response object
	 */
	public function getFileDetails($path) {
		return $this->get('/fs' . self::encodePath($path));
	}
	public function download($path)
    {
        // path names are passed in the URL, so they need encoding
		$path = self::encodePath($path);

        $response = $this->get('/fs-content'.$path);

		return $response;
	}
	public function downloadbyid($id)
    {
        // path names are passed in the URL, so they need encoding
	//	$path = self::encodePath($path);

        $response = $this->get('/fs-content/ids/file/'.$id);

        return $response;
    }
	/**
	 * Create a new folder
	 * @param  string $parentFolder parent folder path including trailing slash
	 * @param  string $name         name of the new folder
	 * @return EgnyteResponse       Response object
	 */
	public function createFolder($parentFolder, $name) {
		$path = self::encodePath($parentFolder . $name);
		return $this->postJSON('/fs' . $path, array('action' => 'add_folder'), array(
			403 => 'User does not have permission to create folder',
			405 => 'A file with the same name already exists'
		));
	}

	protected function get($url, $errorMap = array()) {
		return new EgnyteResponse($this->curl->get($this->baseUrl . $url), $errorMap);
	}

	protected function post($url, $postFields = array(), $errorMap = array()) {
		return new EgnyteResponse($this->curl->post($this->baseUrl . $url, $postFields), $errorMap);
	}

	protected function postJSON($url, $json = array(), $errorMap = array()) {
		$this->curl->headers['Content-Type'] = "application/json";
		return $this->post($url, json_encode($json), $errorMap);
	}


	/**
	 * Encodes paths so they can be used in URLs
	 * @param  string $path Folder path, optionally including file name
	 * @return string       The encoded path
	 */
	public static function encodePath($path) {
		return implode('/',array_map('urlencode', explode('/', $path)));
	}

}

/**
 * A wrapper around the http response that provides easy access to attributes of the response including error information
 */
Class EgnyteResponse {

	public $curlResponse;
	public $statusCode;
	public $body;
	public $errorMap = array(
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		415 => 'Unsupported Media Type',
		500 => 'Internal Server Error',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		596 => 'Service Not Found'
	);


	public function __construct($curlResponse, $errorMap = array()) {
		$this->curlResponse = $curlResponse;
		$this->errorMap = $this->errorMap + $errorMap;
		$this->body = $curlResponse->body;
		$this->statusCode = (int) $this->curlResponse->headers['Status-Code'];
	}

	/**
	 * Whether the request was an error
	 * @return boolean True if error, false if successful
	 */
	public function isError() {
		return $this->statusCode >= 400;
	}

	/**
	 * JSON decode the body of the response
	 * @return StdClass A decoded version of the JSON response.  Null if the response can't be JSON decoded
	 */
	public function getDecodedJSON() {
		return json_decode($this->body);
	}

	/**
	 * Details on errors, should not be called on successful requests
	 * @return array associated array of fields with error information
	 */
	public function getErrorDetails() {
		if($this->statusCode < 400) {
			return new Exception('Request was successful, there are no error details');
		}
		$fields = array(
			'rawBody' => $this->curlResponse->body,
			'jsonBody' => $this->getDecodedJSON(),
			'statusCode' => $this->statusCode,
			'statusCodeText' => (array_key_exists($this->statusCode, $this->errorMap)) ? $this->errorMap[$this->statusCode] : 'Unknown Error'
		);


		if(isset($this->curlResponse->headers['X-Mashery-Error-Code'])) {
			$fields['apiException'] = $this->curlResponse->headers['X-Mashery-Error-Code'];
		}
		return $fields;
	}
}