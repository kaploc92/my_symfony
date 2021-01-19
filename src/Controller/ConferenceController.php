<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ConferenceRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Conference;
use App\Entity\Comment;
use Twig\Environment;
use App\Form\CommentFormType;
use App\Message\CommentMessage;
use Blackfire\Bridge\Symfony\BlackfiredHttpClient;
use Blackfire\Client as BlackfireClient;
use Blackfire\ClientConfiguration;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\MessageBusInterface;

class ConferenceController extends AbstractController
{
	private $twig;
	private $entitymanager;
	private $bus;
	
    public function __construct(Environment $twig, EntityManagerInterface $entitymanager, MessageBusInterface $bus)
    {
        $this->twig = $twig;        
        $this->entitymanager = $entitymanager;
		$this->bus = $bus;
    }

    /**
     * @Route("/", name="homepage")
     */
    public function index(ConferenceRepository $conferenceRepository,$blackfireClient, $blackfireToken): Response
    {
      /*  try {
            $config = new \Blackfire\Profile\Configuration();
            $config->setSamples(3);
            $config->setTitle('Blog Home');
            $clientConfig = new ClientConfiguration();
            $clientConfig->setClientId($blackfireClient);
            $clientConfig->setClientToken($blackfireToken);
            $blackfire = new BlackfireClient($clientConfig);
            $httpClient = new BlackfiredHttpClient(HttpClient::create(), $blackfire);
            $response = $httpClient->request('GET', 'https://www.symfony.com/blog/', [
                'base_uri' => 'https://www.symfony.com/',
                'extra' => [
                    'blackfire' => true,
                ],
            ]);
        }catch(\Exception $e){
            dd($e);
        }*/
        return new Response($this->twig->render('conference/index.html.twig'));
    }
	
	/**
     * @Route("/conference/{slug}", name="conference")
     */
    public function show(Request $request, Conference $conference, CommentRepository $commentRepository, string $photoDir): Response
    {
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){

            $comment->setConference($conference);
            if ($photo = $form['photo']->getData()) {
                $filename = bin2hex(random_bytes(6)).'.'.$photo->guessExtension();
                try {
                    $photo->move($photoDir, $filename);
                } catch (FileException $e) {
                    // unable to upload the photo, give up
                }
                $comment->setPhotoFilename($filename);
            }

            $this->entitymanager->persist($comment);
			$this->entitymanager->flush();

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referer'),
                'permalink' => $request->getUri(),
            ];
            
            $this->bus->dispatch(new CommentMessage($comment->getId(), $context);            

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }
		$offset = max(0, $request->query->getInt('offset', 0));
		$paginator = $commentRepository->getCommentPaginator($conference, $offset);
        return new Response($this->twig->render('conference/show.html.twig', [
		  'conference' => $conference,
		   'comments' => $paginator,
            'previous' => $offset - CommentRepository::PAGINATOR_PER_PAGE,
            'next' => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),
            'comment_form' => $form->createView(),
		]));
    }
}
