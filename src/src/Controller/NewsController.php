<?php

namespace App\Controller;


use App\Repository\ArticleRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;



class NewsController extends AbstractController
{
    private ArticleRepository $articleRepository;

    public function __construct(ArticleRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }

    /**
     * @Route("/", name="all")
     * @return Response
     */
    public function all()
    {
        $news = $this->articleRepository->findAll();
        return $this->render('news/all.html.twig', [
            'news' => $news
        ]);
    }

    /**
     * @Route("/show/{id}", name="show")
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        $item = $this->articleRepository->find($id);
        return $this->render('news/show.html.twig', [
            'item' => $item
        ]);
    }

    /**
     * @Route("/parse", name="parse")
     * @param KernelInterface $kernel
     * @return Response
     * @throws Exception
     */
    public function parse(KernelInterface $kernel)
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'parse:rbk',
        ]);

        $output = new BufferedOutput();
        $application->run($input, $output);
        $content = $output->fetch();

        return $this->render('news/parse.html.twig', [
            'text' => $content
        ]);
    }

}