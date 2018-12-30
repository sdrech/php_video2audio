<?php

namespace App\Service;

use GuzzleHttp\Client;

use transloadit\Transloadit;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Integration with Transloadit Multimedia service via API
 */
class AudioProvider
{
	private const SERVICE_KEY = 'b98444f00baa11e99ff16dafffcf4ae2';
	private const SERVICE_SECRET = '213df4f369bf60fd4516647b7f96b811ecaccd77';

	const API_GET_VIDEO = 'promoVideos/data/search-promo-collection';

	public const KEY_AUDIO_URL = 'audioUrl';
	public const KEY_RESULT_URL = 'assembly_url';
	public const KEY_AUDIO_DETAILS = 'audioDetails';
	public const KEY_RESPONSE_STATUS = 'status';
	public const KEY_RESPONSE_DATA = 'data';
	public const KEY_AUDIO_DATA = 'audioData';

	private $client;
    
	public function __construct(ContainerInterface $container)
	{
		$this->client = new Transloadit([
			"key"   => self::SERVICE_KEY,
			"secret"=> self::SERVICE_SECRET,
		]);
	}

	public function requestAudioFromVideo($url = null)
	{
		try{
			$response = $this->client->createAssembly([
				"params" => [
					"steps" => [
						":original" => [
							"robot" => "/upload/handle"
						],

						"nonmute_video_filtered" => [
						  "use" => [":original"],
						  "robot" => "/file/filter",
						  "result" => true,
						  "accepts" => [
							["\${file.meta.video_codec}", "!=", ""],
							["\${file.meta.audio_codec}", "=", ""]
						  ],
						  "condition_type" => "and",
						  "error_on_decline" => false
						],
						"audio_from_video_extracted" => [
						  "use" => ["nonmute_video_filtered"],
						  "robot" => "/video/encode",
						  "result" => true,
						  "ffmpeg" => [
						  	"vn" => true,
							"codec:v" => "none",
							"map" => ["0", "-0:d?", "-0:s?", "-0:v?"]
						  ],
						  "ffmpeg_stack" => "v3.3.3",
						  "preset" => "mp3",
						  "rotate" => false
						],
						"exported" => [
						  "use" => [
						  	"nonmute_video_filtered",
							"audio_from_video_extracted",
							":original"
						  ],
						  "robot" => "/s3/store",
						  "credentials" => "demo_s3_credentials"
						]
					]
				]
			]);
		} catch(\Exception $e) {
			throw new ConflictHttpException($e->getMessage(), null, 500);
		}

		return [
			self::KEY_AUDIO_URL         => $response->curlInfo[self::KEY_RESULT_URL] ?? null,
			self::KEY_AUDIO_DETAILS     => [
				self::KEY_RESPONSE_STATUS   => $response->data,
				self::KEY_RESPONSE_DATA     => $response->curlInfo
			]
		];
	}

	public function requestResizeImage($file)
	{
		try{
			$files = ["https://ak04-cdn.slidely.com/collections/videos/5b/c5/5bc592fd374e27c9637b23e9--thumb-small.jpg?dv=0"];

			$response = $this->client->createAssembly([
				"files" => $files,
				"params" => [
					"steps" => [
						"thumb" => [
							"use" => ":original",
							"robot" => "/image/resize",
							"width" => 75,
							"height" => 75,
							"resize_strategy" => "fit",
						],
					],
				],
			]);
		} catch(\Exception $e) {
			throw new ConflictHttpException($e->getMessage(), null, 500);
		}

		return $response;
	}

}
