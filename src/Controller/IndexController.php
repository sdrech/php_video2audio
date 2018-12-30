<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IndexController
{
	/*
	 * Get User Manager in fast way
	 */
	private function getManagerUser()
	{
//		return $this->get('manager.user');
	}

	public function indexAction(Request $request)
	{
		$number = rand(0, 555);
//		return $number;

//		return View::create($number, Response::HTTP_CREATED);

		return new Response(
			'<html><body>Lucky number: '.$number.'</body></html>', 200
//			$number
		);
//		$view = $this->view($request, 200);
//		return $this->handleView($view);
	}

}
