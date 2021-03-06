<?php
/**
* @name UserController Service REST pour la gestion des utilisateurs
* @author IDea Factory (dev-team@ideafactory.fr)
* @package UserBundle\Controller
* @version 1.0.1
* 	Modification de la route pour l'utilisateur anonyme
*/
namespace UserBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\View\View;
use UserBundle\Entity\User;
use UserBundle\Entity\Groupe;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Common\Util\ClassUtils;
use UserBundle\Service\TokenService;


class UserController extends FOSRestController {
	
	/**
	 * Instance du repo des utilisateurs
	 * @var unknown
	 */
	private $_repository;
	
	/**
	 * Instance d'un utilisateur complet
	 * @var \UserBundle\Entity\User
	 */
	private $_wholeUser;
	

	/**
	 * Instance du gestionnaire de token JWT
	 * @var TokenService
	 */
	private $tokenService;
	
	/**
	 * Constructeur du contrôleur User
	 */
	public function __construct(TokenService $tokenService) {
	    $this->tokenService = $tokenService;
	}
	
	/**
	 * @Route("/myaccount/{token}", methods={"GET","HEAD"}, name="my-account")
	 */
	public function myAccountAction(Request $request) {
	    $request->setRequestFormat("html");
	    if ($this->authToken($request)) {
	        return $this->render(
	            "@User/Default/account.html.twig"
	        );
	    }
	    
	    // Redirige vers la page de login
	}
	
	/**
	 * @Rest\Post("/account/{id}")
	 * 
	 * @param Request $request
	 */
	public function updateInfoAction(Request $request) {
	    $user = $this->getDoctrine()
	       ->getManager()
	       ->getRepository("UserBundle:User")
	       ->find($request->get("id"));
	    
	       if ($user) {
	           // Génère le contenu à partir des données se terminant par _
	           $content = [];
	           
	           $datas = $request->request->all();
	           
	           foreach ($datas as $param => $value) {
	               if (substr($param, -1) === "_") {
	                   $param = substr($param, 0, strlen($param) - 1);
	                   $content[$param] = $value;
	               }
	           }
	           $user->setContent(json_encode($content));
	           
	           $entityManager = $this->getDoctrine()->getManager();
	           $entityManager->persist($user);
	           $entityManager->flush();
	           
	           return new View("Vos informations ont bien été mises à jour", Response::HTTP_OK);
	       }
	       
	       return new View("Cet utilisateur n'est pas connu dans notre système", Response::HTTP_FORBIDDEN);
	}
	
	/**
	 * @Rest\Post("/account/password/update")
	 *
	 * @param Request $request
	 */
	public function updatePasswordAction(Request $request) {
	    if ($this->authToken($request)) {
	        // Regénère le mot de passe
	        $this->_wholeUser->setSecurityPass($this->_createPassword($request->get("password"), $this->_wholeUser->getSalt()));
	        
	        $entityManager = $this->getDoctrine()->getManager();
	        $entityManager->persist($this->_wholeUser);
	        $entityManager->flush();
	        
	        return new View("Vos informations ont bien été mises à jour", Response::HTTP_OK);
	    }
	    
	    return new View("Une erreur est survenue lors de la mise à jour de votre mot de passe", Response::HTTP_FORBIDDEN);
	}
	
	/**
	 * @Rest\Put("/register")
	 */
	public function registerAction(Request $request) {
		
	    if (!$request->get("suckrobot")) {
    		if (!$this->_alreadyExists($request->get("email"))) {
    			$this->_wholeUser = new User();
    			
    			// Génère le sel de renforcement du mot de passe
    			$salt = $this->_makeSalt();
    			
    			// Génère le contenu à partir des données se terminant par _
    			$content = [];
    			
    			$datas = $request->request->all();
    			
    			foreach ($datas as $param => $value) {
    			    if (substr($param, -1) === "_") {
    			        $param = substr($param, 0, strlen($param) - 1);
    			        $content[$param] = $value;
    			    }
    			}
    
    			
    			$this->_wholeUser
    				->setLogin($request->get("email"))
    				->setSecurityPass($this->_createPassword($request->get("password"), $salt))
    				->setSalt($salt)
    				->setIsValid(false)
    				->setCreatedAt(new \DateTime())
    				->setLastLogin(new \DateTime())
    				->setValidatedAt(new \DateTime())
    				->setContent(json_encode($content))
    				->setGroup($this->_getCustomerGroup());
    			
    			// Fait persister la donnée
    			$entityManager = $this->getDoctrine()->getManager();
    			$entityManager->persist($this->_wholeUser);
    			$entityManager->flush();
    			
    			return new View($this->_format($this->_wholeUser), Response::HTTP_CREATED);
    		}
    		
    		return new View("Un compte avec cet email existe déjà sur ce site", Response::HTTP_CONFLICT);
	    } else {
	        return new View("L'accès à cette boutique n'est pas autorisée aux robots", Response::HTTP_FORBIDDEN);
	    }
	}
	
	/**
	 * @Rest\Post("/signin")
	 * @param Request $request Requête envoyée
	 */
	public function signinAction(Request $request) {
		
		if ($request) {
			if (!$this->_checkLogin($request->get("login"))) {
				return new View("L'adresse e-mail est inconnue ou votre compte a été invalidé", Response::HTTP_FORBIDDEN);
			}
			
			if (!$this->_validPassword($request->get("password"))) {
				return new View("Votre mot de passe est incorrect, veuillez réessayer s'il vous plaît", Response::HTTP_FORBIDDEN);
			}
			
			
			return new View($this->_format($this->_wholeUser), Response::HTTP_OK);
		}
	}

	/**
	 * @Rest\Post("/account/address/billing")
	 * @param Request $request Requête envoyée
	 */
	public function addBillingAddress(Request $request){
	    if ($this->authToken($request)) {
	        
	        $account = $this->_wholeUser;
	        
    	    // Définit une nouvelle adresse de facturation
    	    $billingAddress = [
    	        "address" => $request->get("address"),
    	        "zipcode" => $request->get("zipcode"),
    	        "city" => $request->get("city"),
    	        "country" => $request->get("country"),
    	        "sameAsDelivery" => $request->get("asDelivery") ? true : false
    	    ];
    	    
    	    if ($request->get("asDelivery")) {
    	        $deliveryAddress = [
    	            "name" => "Principale",
    	            "address" => $request->get("address"),
    	            "zipcode" => $request->get("zipcode"),
    	            "city" => $request->get("city"),
    	            "country" => $request->get("country"),
    	        ];
    	        $account->addDeliveryAddress($deliveryAddress);
    	    }
    	    
    	    $account->addBillingAddress($billingAddress);
    	    
    	    
    	    $entityManager = $this->getDoctrine()->getManager();
    	    $entityManager->persist($account);
    	    
    	    $entityManager->flush();
    	    
    	    // Recharge la ligne utilisateur
    	    $user = $entityManager
    	       ->getRepository("UserBundle:User")
    	       ->find($this->_wholeUser->getId());

    	    
    	    return new View($user->getRawContent()["addresses"], Response::HTTP_OK);
	    }
	    return new View("Le compte n'est plus actif, ou a été invalidé", Response::HTTP_FORBIDDEN);
	}

	/**
	 * @Rest\Put("/account/address/billing")
	 * @param Request $request Requête envoyée
	 */
	public function updBillingAddress(Request $request){
	    if ($this->authToken($request)) {
	        
	        $account = $this->_wholeUser;
	        
	        $billingAddress = $account->getRawContent()["addresses"]["billing"];
	        
	        // Définit une nouvelle adresse de facturation
	        $billingAddress["address"] = $request->get("address");
	        $billingAddress["zipcode"] = $request->get("zipcode");
	        $billingAddress["city"] = $request->get("city");
	        $billingAddress["country"] = $request->get("country");
	        
	        $account->addBillingAddress($billingAddress);
	        
	        
	        $entityManager = $this->getDoctrine()->getManager();
	        $entityManager->persist($account);
	        
	        $entityManager->flush();
	        
	        // Recharge la ligne utilisateur
	        $user = $entityManager
	           ->getRepository("UserBundle:User")
	           ->find($this->_wholeUser->getId());
	        
	        
	        return new View($user->getRawContent()["addresses"], Response::HTTP_OK);
	    }
	    return new View("Le compte n'est plus actif, ou a été invalidé", Response::HTTP_FORBIDDEN);
	}
	
	/**
	 * @Rest\Post("/account/address/delivery")
	 * @param Request $request Requête envoyée
	 */
	public function addDeliveryAddress(Request $request){
	    if ($this->authToken($request)) {
	        
	        $account = $this->_wholeUser;
	        
	        // Définit une nouvelle adresse de facturation
	        $billingAddress = [
	            "name" => $request->get("name"),
	            "address" => $request->get("address"),
	            "zipcode" => $request->get("zipcode"),
	            "city" => $request->get("city"),
	            "country" => $request->get("country"),
	        ];
	        

	        
	        $account->addDeliveryAddress($billingAddress);
	        
	        $entityManager = $this->getDoctrine()->getManager();
	        $entityManager->persist($account);
	        
	        $entityManager->flush();
	        
	        $account = $this->getDoctrine()
	           ->getManager()
	           ->getRepository("UserBundle:User")
	           ->find($this->_wholeUser->getId());
	        
            $deliveryAddresses = $account->getRawContent()["addresses"]["delivery"];
	        $length = count($deliveryAddresses);
	        
	        // Retourne la dernière adresse de livraison créée
	        return new View($deliveryAddresses[$length-1], Response::HTTP_OK);
	    }
	    
	    return new View("Le compte n'est plus actif, ou a été invalidé", Response::HTTP_FORBIDDEN);
	    
	}
	
	/**
	 * @Rest\Get("/nouser")
	 * @param void
	 */
	public function loginAction() {
		
		if (!$this->_checkLogin("anonymous")) {
			return new View("L'accès anonyme n'est pas autorisé sur cette application", Response::HTTP_FORBIDDEN);
		}
			
			
		return new View($this->_format($this->_wholeUser), Response::HTTP_OK);
	}
	
	/**
	 * @Rest\Get("/token/{token}")
	 * @param Request $request
	 */
	public function getUserFromToken(Request $request) {
	    $authGuard = $this->tokenService->tokenAuthentication($request);
	    
	    if ($authGuard["code"] === Response::HTTP_OK) {
	        $this->_wholeUser = $this->getDoctrine()
	           ->getManager()
	           ->getRepository("UserBundle:User")
	           ->find($authGuard["user"]);
	        return new View($this->_format($this->_wholeUser, $request->get("token")));
	    }
	    
	    return new View("Token non valide ou expiré", $authGuard["code"]);
	}
	
	/**
	 * Retourne l'utilisateur courant à partir du token JWT
	 * @param Request $request
	 * @return bool
	 */
	private function authToken(Request $request): bool {
	    $authGuard = $this->tokenService->tokenAuthentication($request);
	    
	    if ($authGuard["code"] === Response::HTTP_OK) {
	        $this->_wholeUser = $this->getDoctrine()
	           ->getManager()
	           ->getRepository("UserBundle:User")
	           ->find($authGuard["user"]);
	        return true;
	    }
	    
	    return false;
	}
	
	/**
	 * Détermine si le login saisi n'existe pas déjà
	 * @param string $login
	 * @return bool
	 */
	private function _alreadyExists(string $login): bool {
		$this->_wholeUser = $this->getDoctrine()
			->getManager()
			->getRepository("UserBundle:User")
			->findOneBy(["login" => $login]);
		
		if ($this->_wholeUser) {
			return true;
		}
		
		return false;
	}
	/**
	 * Vérifie l'existence du login et sa validité
	 * @return boolean
	 */
	private function _checkLogin(string $login): bool {
		$this->_wholeUser = $this->getDoctrine()
			->getManager()
			->getRepository("UserBundle:User")
			->findOneBy(["login" => $login]);
		
		if ($this->_wholeUser) {
			if ($this->_wholeUser->getIsValid()) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Vérifie le mot de passe avec la clé de renforcement
	 * @param string $password
	 * @return boolean
	 */
	private function _validPassword(string $password): bool {
		$saltedPassword = $this->_wholeUser->getSalt() . $password . $this->_wholeUser->getSalt();
		
		if (md5($saltedPassword) === $this->_wholeUser->getSecurityPass()) {
			return true;
		}
		
		return false;
	}

	/**
	 * Récupère ou crée le groupe de l'utilisateur identifié
	 * @param int $id
	 * @return \UserBundle\Controller\UserBundle\Entity\Groupe
	 */
	private function _getCustomerGroup(int $id = null) {
		if (is_null($id)) {
			$group = $this->getDoctrine()
				->getManager()
				->getRepository("UserBundle:Groupe")
				->findOneBy(["libelle" => "customer"]);
			
			if (!$group) {
				$group = new UserBundle\Entity\Groupe();
				$group
					->setLibelle("customer")
					->setCanBeDeleted(false);
				// Assurer la persistence du groupe
				$entityManager = $this->getDoctrine()->getManager();
				$entityManager->persist($group);
				$entityManager->flush();
			}
		} else {
			// Retourne le groupe de l'utilisateur à partir de son id
			$group = $this->getDoctrine()
				->getManager()
				->getRepository("UserBundle:Groupe")
				->find($id);
		}
		
		return $group;
		
	}
	
	/**
	 * Génère un sel aléatoire
	 * @return string
	 * @todo Créer un service pour gérer ce type de traitement
	 */
	private function _makeSalt(): string {
		$chars = [
			"a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z",
			"A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z",
			"0", "1", "2", "3", "4", "5", "6", "7", "8", "9",
			"*", "-", "+", "/", "#", "@", "^", "|"
		];
		
		$saltChars = [];
		
		for ($i = 0; $i < 10; $i++) {
			$random = rand(0, 69);
			$saltChars[$i] = $chars[$random];
		}
		
		return join("",$saltChars);
	}
	
	/**
	 * Retourne le mot de passe renforcé en hash md5
	 * @param string $password
	 * @param string $salt
	 * @return string
	 */
	private function _createPassword(string $password, string $salt): string {
		return md5($salt.$password.$salt);
	}
	
	/**
	 * Retourne le formatage d'un utilisateur complet
	 * @param Entity $userEntity
	 * @return array
	 */
	private function _format($userEntity) {
		$datas = [];
		
		$datas["id"] = $userEntity->getId();
		
		$datas["login"] = $userEntity->getLogin();
		
		$datas["token"] = $this->tokenService->generate($this->_wholeUser);
		
		// Traite le contenu, pour récupérer les données cohérentes
		$jsonContent = $userEntity->getContent();
		
		if ($jsonContent !== null) {
			$datas["name"] = $jsonContent->firstName .
				" " . $jsonContent->lastName;
			
			$datas["userDetails"] = $userEntity->getRawContent();
		}
		
		// Traite les options de menu
		$group = $userEntity->getGroup();
		
		$menus = [];
		if ($group->getMenus()) {
			foreach($group->getMenus() as $menu) {
				$menus[] = [
					"id" => $menu->getId(),
					"slug" => $menu->getSlug(),
					"region" => $menu->getRegion(),
					"content" => $menu->getContent(),
					"options" => $menu->categoriesToArray()
				];
			}
		}
		
		$datas["menus"] = $menus;
		
		return $datas;
	}
}
