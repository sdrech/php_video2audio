<?php

namespace App\Service;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Integration with FFMpeg Multimedia service via API
 */
class MultimediaConverter
{
	public const KEY_MEDIA_URL = 'mediaUrl';
	public const KEY_MEDIA_DETAILS = 'mediaDetails';

	private $converter, $exception;
	private $config = [
		'ffmpeg.binaries'  => '/opt/local/ffmpeg/bin/ffmpeg',
		'ffprobe.binaries' => '/opt/local/ffmpeg/bin/ffprobe',
		'timeout'          => 3600, // The timeout for the underlying process
		'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
	];
    
	public function __construct(ContainerInterface $container)
	{
		try{
			$this->converter = FFMpeg::create($this->config);
		} catch(\Exception $e) {
			$this->exception = $e->getMessage() . ' in class '. self::class;
		}
	}

	public function getAudioFromVideo($file)
	{
		$result = [
			self::KEY_MEDIA_URL     => null,
			self::KEY_MEDIA_DETAILS => null
		];

		try{
			$video = $this->converter->open($file);

			// Extract the audio into a new file
			$audioData = $video->save(new Mp3(), 'audio.mp3');
			$result[self::KEY_MEDIA_URL] = $audioData->getPathfile();

		} catch(\Throwable $t) {
			$result[self::KEY_MEDIA_DETAILS] = $this->exception;

		}

		return $result;
	}

}
