<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{
	/**
	 * @Route("/menu")
	 */
	public function indexAction()
	{
		return $this->render('MenuBundle:Default:index.html.twig');
	}
    
}
