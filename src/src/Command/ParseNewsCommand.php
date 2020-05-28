<?php


namespace App\Command;


use App\Entity\Article;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class ParseNewsCommand extends Command
{
    protected static $defaultName = 'parse:rbk';

    private HttpClientInterface $httpClient;

    private EntityManagerInterface $entityManager;

    const WEBSITE_URL = "https://www.rbc.ru/";

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->httpClient = HttpClient::create();
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->entityManager->getConnection();
        try {
            $platform   = $connection->getDatabasePlatform();
            $connection->executeUpdate($platform->getTruncateTableSQL('article', true));
        } catch (DBALException $e) {
            $output->writeln("Unable to truncate the articles table");
            return -1;
        }

        try {
            $response = $this->httpClient->request('GET', self::WEBSITE_URL);
            $content = $response->getContent();
        } catch (Throwable $e) {
            $output->writeln("Unable to reach the news site");
            return -1;
        }

        $indexCrawler = new Crawler($content);
        $indexCrawler = $indexCrawler->filter('#js_news_feed_banner .js-news-feed-list')->children('.news-feed__item');

        foreach ($indexCrawler as $domElement) {
            $link = $domElement->attributes->getNamedItem('href')->nodeValue;

            $article = $this->parseLink($link);
            if (!$article) {
                $output->writeln("Was not able to parse the link: {$link}\n");
                continue;
            }

            $this->entityManager->persist($article);
            $this->entityManager->flush();
        }

        $output->writeln("Parsing processing is completed");
        return 0;
    }

    /**
     * @param string $link
     * @return Article|null
     */
    private function parseLink(string $link): ?Article
    {
        try {
            $response = $this->httpClient->request('GET', $link);
            $content = $response->getContent();
        } catch (Throwable $e) {
            return null;
        }

        $itemCrawler = new Crawler($content);
        $text = "";
        $title = $itemCrawler->filter('.article__header__title')->text('');
        foreach ($itemCrawler->filter('.article__content .article__text p') as $textNode) {
            $text .= $textNode->nodeValue;
        }
        if (!$title || !$text) {
            return null;
        }

        try {
            $imageUrl = $itemCrawler->filter('.article__main-image img')->image()->getUri();
        } catch (Throwable $e) {
            $imageUrl = ""; // there is no main image in the article
        }

        $article = new Article();
        $article
            ->setTitle($title)
            ->setContent($text);
        if ($imageUrl) {
            $article->setImageUrl($imageUrl);
        }
        return $article;
    }

}