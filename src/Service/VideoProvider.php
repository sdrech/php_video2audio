<?php

namespace App\Service;

use GuzzleHttp\Client;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Integrator with Slidely Promo APIs
 */
class VideoProvider
{
    //
	private const METHOD_GET = 'GET';
	private const SOURCE_URL = 'https://slide.ly/';
	private const CLIENT_TOKEN = 'slidely_client';
	private const MAX_REQ_ATTEMPTS = 5;

	// Finally will be requested https://slide.ly/promoVideos/data/search-promo-collection?keyword=bank&page=1&sort_order=best_match&type=all&limit=1
	const API_GET_VIDEO = 'promoVideos/data/search-promo-collection';

	public const KEY_HTTP_STATUS_CODE = 'statusCode';
	public const KEY_RESPONSE_DATA = 'responseData';
	public const KEY_VIDEO_ID = 'videoId';
	public const KEY_PREVIEW_URL = 'previewUrl';
	public const KEY_VIDEO_URL = 'videoUrl';
	public const DEFAULT_SEARCH_TAG = 'fun';

	private $client;
	private $apiToken;  // in case of service allows access to authorized clients only - Token should be required
    private $logger;
    
	public function __construct(ContainerInterface $container)
	{
//		$logger = $container->get('logger');

		$settings = [
			'base_uri' => self::SOURCE_URL
		];
		
		$client = new Client($settings);
		$this->client = $client;
		$this->apiToken = self::CLIENT_TOKEN;
	}

	private function buildUrl($commonUrl, $id = null)
	{
		if (empty($id)) {
			return $commonUrl;
		}

		return str_replace('{id}', $id, $commonUrl);
	}
	
	private function buildOptions($jsonBody = null, $queryParams = [])
	{
		$options['headers'] = [
		    'Token'     => $this->apiToken,
			'Content-Type'  => 'application/json'
		];

		if (!is_null($jsonBody)) {
			$options['json'] = $jsonBody;
		}
		
		if (!empty($queryParams)) {
		    $options['query'] = $queryParams;
		}
		
		return $options;
	}

	protected function requestDataByKeyword($keyword, $limit, $order = 'best_match', $type = 'all')
	{
		$queryParams = [
			'keyword'   => $keyword,
			'limit'     => $limit,
			'sort_order'=> $order,
			'type'      => $type

		];

		$request = [
			"method"  => self::METHOD_GET,
			"url"     => $this->buildUrl(self::API_GET_VIDEO),
			"options" => $this->buildOptions(null, $queryParams)
		];

		try{
			$response = $this->client->request(
				$request["method"],
				$request["url"],
				$request["options"]
			);
		} catch(BadResponseException $e) {
//			$this->logFailedRequest($request,$e);
			throw new ConflictHttpException('SERVICE_PROVIDER_IS_UNAVAILABLE', null, 500);
		}

		return [
//			$request,
			self::KEY_HTTP_STATUS_CODE  => $response->getStatusCode(),
			self::KEY_RESPONSE_DATA     => json_decode($response->getBody()->getContents(), true)
		];
	}

	public function getListByKeyword($keyword = null, $limit = 1)
	{
		$result = [];
		$data = $this->getDataByKeyword($keyword, $limit);

		foreach ($data as $video) {
//			$result[] = $video;
			$result[] = [
				self::KEY_VIDEO_ID  => $video[self::KEY_VIDEO_ID],
				self::KEY_VIDEO_URL => $video[self::KEY_PREVIEW_URL]
			];
		}
		return $result;
	}

	public function getSingleItemByKeyword($keyword = null)
	{
		$result = [];
		$data = $this->getDataByKeyword($keyword, 1);

		if (empty($data)) {
			return $result;
		}

		return [
			self::KEY_VIDEO_ID  => $data[0][self::KEY_VIDEO_ID],
			self::KEY_VIDEO_URL => $data[0][self::KEY_PREVIEW_URL]
		];
	}

	protected function getDataByKeyword($keyword, $limit)
	{
		$keyword = empty($keyword) ? self::DEFAULT_SEARCH_TAG : $keyword;
		$responseItemRoot = 'response';
		$responseItemStatus = 'success';
		$responseItemBody = 'body';
		$responseItemVideos = 'videos';
		$data = null;

		$positiveResponse = function () use ($keyword, $limit, &$data) {
			$response = $this->requestDataByKeyword($keyword, $limit);
			if ($data[self::KEY_HTTP_STATUS_CODE] > 202) {
				return null;
			}

			$data = $response;
			return true;
		};

		while (!$positiveResponse()) {
			// call remote service several times if response is unsuccessful
			for ($i=1; $i<self::MAX_REQ_ATTEMPTS; $i++) {
				$positiveResponse();
			}
		}

		if (is_null($data) || !array_key_exists($responseItemRoot, $data[self::KEY_RESPONSE_DATA])) {
			throw new ConflictHttpException('REQUESTED_DATA_IS_WRONG', null, 400);
		}
		if (!$data[self::KEY_RESPONSE_DATA][$responseItemRoot][$responseItemStatus]) {
			throw new ConflictHttpException('RESPONSE_STRUCTURE_IS_CHANGED', null, 405);
		}

		if (!isset($data[self::KEY_RESPONSE_DATA][$responseItemRoot][$responseItemBody][$responseItemVideos])) {
			return [];
		}

		return $data[self::KEY_RESPONSE_DATA][$responseItemRoot][$responseItemBody][$responseItemVideos];
	}

	/**
	 * 
	 * @param $request
	 * @param BadResponseException $exception
	 */
	private function logFailedRequest($request, $exception)
	{
	    
	    $responseException = ["status"   => $exception->getResponse()->getStatusCode(),
        	                  "message"  => $exception->getMessage(),
	                          "response" => $exception->getResponse()];
	    
	    $result = ["request"   => $request,
	               "exception" => $responseException];
	    
	    $this->logger->error("-- Response failed. \n" .
	                   "Result: " . json_encode($result) . "\n");
	    
	}
}
