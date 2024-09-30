<?php

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UrlController extends AbstractController
{
    private string $jsonFilePath = __DIR__.'/../../urls.json';

    #[Route('/urls', name: 'urls', methods: ['POST'])]
    public function saveUrl(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid input data format'], 400);
        }

        $savedUrls = [];

        foreach ($data as $urlData) {
            if (isset($urlData['url'], $urlData['created_at'])) {
                try {
                    $createdAt = new \DateTime($urlData['created_at']);
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => 'Invalid date format for ' . $urlData['url']], 400);
                }
                $savedUrls[] = ['url'=>$urlData['url'],
                    'created_at'=>$createdAt->format('Y-m-d H:i:s')
                ];
            } else {
                return new JsonResponse(['error' => 'Missing required fields'], 400);
            }
        }
        file_put_contents($this->jsonFilePath, json_encode($savedUrls, JSON_PRETTY_PRINT));

        return new JsonResponse([
            'status' => 'success',
            'saved_urls' => $savedUrls
        ], 201);
    }

    #[Route("/api/urls", name: "get_urls", methods: ["GET"])]
    public function getStatistic(Request $request): JsonResponse
    {
        if (!file_exists($this->jsonFilePath)) {
            return new JsonResponse(['urls' => []], 200);
        }

        $data = json_decode(file_get_contents($this->jsonFilePath), true);

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $domain = $request->query->get('domain');

        if ($startDate && $endDate) {
            $data = array_filter($data, function ($url) use ($startDate, $endDate) {
                $createdAt = new \DateTime($url['created_at']);
                $start = new \DateTime($startDate);
                $end = new \DateTime($endDate);
                return $createdAt >= $start && $createdAt <= $end;
            });
        }

        if ($domain) {
            $data = array_filter($data, function ($url) use ($domain) {
                $parsedUrl = parse_url($url['url']);
                $urlDomain = explode('.', $parsedUrl['host']);
                return isset($parsedUrl['host']) && $urlDomain[1] === $domain;
            });

            $uniqueUrls = array_unique(array_column($data, 'url'));
            return new JsonResponse(['unique_url_count' => count($uniqueUrls)], 200);
        }

        $uniqueUrls = array_unique(array_column($data, 'url'));

        return new JsonResponse(['unique_urls' => $uniqueUrls], 200);
    }
}