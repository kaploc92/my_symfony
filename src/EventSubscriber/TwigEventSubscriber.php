<?php

namespace App\EventSubscriber;

use App\Repository\ConferenceRepository;
use App\Entity\Conference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Twig\Environment;
use Predis\Client as PRClient;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;

class TwigEventSubscriber implements EventSubscriberInterface
{
	private $twig;
    private $conferenceRepository;
    private $serializer;
    private $pcrlient;
	
	public function __construct(Environment $twig, ConferenceRepository $conferenceRepository)
    {
        $this->twig = $twig;
        $this->conferenceRepository = $conferenceRepository;
		$this->prclient = new PRClient();
		$encoders = [new JsonEncoder()];
		$defaultContext = [
        AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
             return $object->getCity();
         },
		 AbstractNormalizer::IGNORED_ATTRIBUTES => ['comments'],
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
		$normalizers = [$normalizer];
		$this->serializer = new Serializer($normalizers, $encoders);
    }
	
    public function onControllerEvent(ControllerEvent $event)
    {		
	    $conferences = [];		
		if(is_null($this->prclient->get('conferences'))){	
			$this->prclient->set('conferences',$this->serializer->serialize($this->conferenceRepository->findAll(), 'json'));
		}
		if(!is_null($this->prclient->get('conferences'))){	
		$conferences = json_decode($this->prclient->get('conferences'),TRUE);		
		}
		$this->twig->addGlobal('conferences', $conferences);		
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.controller' => 'onControllerEvent',
        ];
    }
}
