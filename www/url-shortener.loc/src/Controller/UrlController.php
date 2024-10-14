<?php

namespace App\Controller;

use App\Entity\Url;
use App\Repository\UrlRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


class UrlController extends AbstractController
{
    /**
     * @Route("/encode-url", name="encode_url")
     */
    public function encodeUrl(Request $request): JsonResponse
    {
        $url = new Url();
        $url->setUrl($request->get('url'));

        /** @var UrlRepository $urlRepository */
        $urlRepository = $this->getDoctrine()->getRepository(Url::class);
        $urlFromHash = $urlRepository->findOneByUrl($url->getUrl());
        if (empty ($urlFromHash)) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($url);
            $entityManager->flush();
            return $this->json([
                'hash' => $url->getHash(),
                'url' => 'given not from database',
                'date_of_creation' => $url->getCreatedDate(),
            ]);
        } elseif (!$this->isActual($urlFromHash)) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->flush();
            return $this->json([
                'hash' => $url->getHash(),
                'url' => 'given not from database',
                'date_of_creation' => $url->getCreatedDate(),
            ]);
        }
        return $this->json([
            'hash' => $urlFromHash->getHash(),
            'url' => 'given from database',
            'date_of_creation' => $urlFromHash->getCreatedDate()
        ]);


    }

    /**
     * @Route("/decode-url", name="decode_url")
     */
    public function decodeUrl(Request $request): JsonResponse
    {
        $url = $this->decode($request);
        if (empty ($url->getUrl())) {
            return $this->json([
                'error' => 'Non-existent hash.'
            ]);
        }
        if($this->isActual($url)) {
            return $this->json([
                "url" => $url->getUrl()
            ]);
        } else return $this->json([
            'error' => 'Token lifetime has expired'
        ]);

    }

    /**
     * @Route("/gourl", name="gourl")
     */
    public function goToUrl(Request $request)
    {
       $url = $this->decode($request);
        if (empty ($url->getUrl())) {
            return $this->json([
                'error' => 'Non-existent hash.'
            ]);
        }
        if($this->isActual($url)) {
            return $this->redirect($url->getUrl(), 301);
        } else return $this->json([
            'error' => 'Token lifetime has expired'
        ]);

    }

    private function decode(Request $request): Url
    {
        /** @var UrlRepository $urlRepository */
        $urlRepository = $this->getDoctrine()->getRepository(Url::class);
        return $urlRepository->findOneByHash($request->get('hash'));

    }

    private function isActual(Url $url): bool
    {
        $lifetime = $this->getParameter('lifetime');
        $diff = $url->getCreatedDate()->diff(new \DateTimeImmutable())->s;
        if($diff>=$lifetime) {
            return false;
        } else return true;
    }

}
