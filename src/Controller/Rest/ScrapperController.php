<?php

namespace App\Controller\Rest;

use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\VideoProvider;
use App\Service\AudioProvider;
use App\Service\MultimediaConverter;

class ScrapperController extends FOSRestController
{
	/**
	 * @var VideoProvider
	 */
	private $videoProvider;

	/**
	 * @var AudioProvider
	 */
	private $audioProvider;

	/**
	 * @var MultimediaConverter
	 */
	private $mediaConverter;

	/**
	 * @param VideoProvider $videoService
	 */
	public function __construct(VideoProvider $videoService, AudioProvider $audioService, MultimediaConverter $mediaService)
	{
		$this->videoProvider = $videoService;
		$this->audioProvider = $audioService;
		$this->mediaConverter = $mediaService;
	}

	/**
	 * Creates an Article resource
	 * @Rest\Get("/promo2mp3")
	 * @param Request $request
	 * @return View
	 */
	public function getListAction(Request $request)
	{
		$tag = $request->query->get('tag', VideoProvider::DEFAULT_SEARCH_TAG);

		// get Video from Slidely service
		$videoData = $this->videoProvider->getSingleItemByKeyword($tag);

		// sample request to Transloadit service which accept only source_file or AWS_S3 (and not simple URL)
		$audioData = $this->audioProvider->requestAudioFromVideo($videoData[VideoProvider::KEY_VIDEO_URL]);

		// sample request to FFMpeg service which accept only source_file (and not simple URL)
		$mediaData = $this->mediaConverter->getAudioFromVideo($videoData[VideoProvider::KEY_VIDEO_URL]);

		$response = [
			VideoProvider::KEY_VIDEO_ID             => $videoData[VideoProvider::KEY_VIDEO_ID],
			VideoProvider::KEY_VIDEO_URL            => $videoData[VideoProvider::KEY_VIDEO_URL],
			AudioProvider::KEY_AUDIO_URL            => $audioData[AudioProvider::KEY_AUDIO_URL],
			AudioProvider::KEY_AUDIO_DETAILS        => $audioData[AudioProvider::KEY_AUDIO_DETAILS],
			MultimediaConverter::KEY_MEDIA_URL      => $mediaData[MultimediaConverter::KEY_MEDIA_URL],
			MultimediaConverter::KEY_MEDIA_DETAILS  => $mediaData[MultimediaConverter::KEY_MEDIA_DETAILS]
		];

		return View::create($response, Response::HTTP_CREATED);
	}

}
