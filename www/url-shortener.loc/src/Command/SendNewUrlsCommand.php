<?php


namespace App\Command;

use App\Entity\Url;
use App\Entity\SentUrl;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SendNewUrlsCommand extends Command
{
    protected static $defaultName = 'app:send-new-urls';
    private $entityManager;
    private $httpClient;
    private $endpoint;

    public function __construct(EntityManagerInterface $entityManager, HttpClientInterface $httpClient, string $endpoint)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->endpoint = $endpoint;
    }

    protected function configure()
    {
        $this
            ->setDescription('Send new URLs to external endpoint');
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $urlRepository = $this->entityManager->getRepository(Url::class);
        $sentUrlRepository = $this->entityManager->getRepository(SentUrl::class);

        $urls = $urlRepository->findAll();

        foreach ($urls as $url) {
            if (!$sentUrlRepository->findByHash($url->getHash())) {
                $response = $this->httpClient->request('POST', $this->endpoint, [
                    'json' => [
                        'url' => $url->getUrl(),
                        'created_at' => $url->getCreatedDate()->format('Y-m-d H:i:s')
                    ]
                ]);

                if ($response->getStatusCode() === 200) {
                    $sentUrl = new SentUrl();
                    $sentUrl->setHash($url->getHash());
                    $sentUrl->setSentAt(new \DateTime());

                    $this->entityManager->persist($sentUrl);
                    $this->entityManager->flush();
                }
            }
        }

        return Command::SUCCESS;
    }
}

