<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontendController extends AbstractController
{
    #[Route('/', name: 'app_frontend', methods: ['GET'])]
    public function index(): Response
    {
        $htmlPath = $this->getParameter('kernel.project_dir') . '/public/client.html';

        return new Response(
            file_get_contents($htmlPath),
            Response::HTTP_OK,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
