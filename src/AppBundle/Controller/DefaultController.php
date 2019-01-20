<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use ContentBundle\ContentBundle;
use Symfony\Component\Asset\Packages;

class DefaultController extends Controller
{
    
	/**
	 * @Route("/", defaults={"_format"="html"}, name="home")
	 */
	public function indexAction(Request $request, Packages $assetPackage) {
	    $request->setRequestFormat("html");
	    
	    $editorial = $this->getEditorial();
	    
	    // Promotions en cours...
	    $promotions = $this->getBySlug("promotions");
	    $products = [];
	    $promotion = [];
	    
	    if ($promotions) {
	        $catToArticles = $promotions->getArticles();
	        foreach($catToArticles as $catToArticle) {
	            $products[] = [
	                "category" => $catToArticle->getCategorie(),
	                "product" => $catToArticle->getArticle(),
	                "image" => $catToArticle->getArticle()->getMainImage()
	            ];
	        }
	        if (count($products)) {
	            $promotion = $products[0];
	        }
	    }
	    
	    // Nouveautés
	    $newProductsCategory = $this->getBySlug("news");
	    $newProduct = [];
	    if ($newProductsCategory) {
	        $categoryContent = $newProductsCategory->getContent();
	        
	        $catToArticles = $newProductsCategory->getArticles();
	        if (count($catToArticles)) {
    	        $catToArticle = $catToArticles[0];
    	        $product = [
    	           "product" => $catToArticle->getArticle(),
    	            "image" => $catToArticle->getArticle()->getMainImage()
    	        ];
    	        $newProduct["category"] = $newProductsCategory;
    	        $newProduct["product"] = $product;
	        }
	    }
	    
	    
	    // Meilleures ventes
        $topSales = $this->_topSales();
        $bestSale = [];
        if (count($topSales)) {
            $bestSale = $topSales[0];
        }
	    
	    // Images de slider
	    $sliderImages = $this->getSliderImages($assetPackage);
	    
	    // Tips : newsletter, livraison, paiement
	    $tipsCategory = $this->getBySlug("tips");
	    $tipArticles = [];
	    if ($tipsCategory) {
	        $catToArticles = $tipsCategory->getArticles();
	        
	        if (count($catToArticles)) {
	            foreach ($catToArticles as $catToArticle) {
	                $tipArticles[] = $catToArticle->getArticle();
	            }
	        }
	    }
	    
		return $this->render("@App/Default/index.html.twig",
		  [
		      "sliderImages" => $sliderImages,
		      "editorial" => $editorial,
		      "promotions" => $promotion,
		      "newProduct" => $newProduct,
		      "bestSale" => $bestSale,
		      "tips" => $tipArticles
		  ]
		);
	}
	
	/**
	 * Retourne l'article spécifique éditorial
	 * 
	 */
	private function getEditorial() {
	    $content = null;
	    $editorial = $this->getDoctrine()
	       ->getManager()
	       ->getRepository("ContentBundle:Article")
	       ->findOneBySlug("editorial");
	   
	   if ($editorial) {
	       $content = $editorial;        
	   }
	   
	   return $content;
	    
	}
	
	/**
	 * Retourne l'article spécifique Découverte des boxes
	 *
	 */
	private function getDiscovering() {
	    $content = null;
	    $discover = $this->getDoctrine()
	       ->getManager()
	       ->getRepository("ContentBundle:Article")
	       ->findOneBySlug("box-discovering");
	    
	    if ($discover) {
	        $content = $discover->getContent();
	    }
	    
	    return $content;
	    
	}
	
	/**
	 * Retourne la catégorie par l'intermédiaire du slug
	 * @param string $slug
	 * @return \MenuBundle\Entity\Categorie|NULL
	 */
	private function getBySlug(string $slug) {
	    $category = $this->getDoctrine()
	    ->getManager()
	    ->getRepository(\MenuBundle\Entity\Categorie::class)
	    ->findOneBy(["slug" => $slug]);
	    
	    return $category;
	}
	
	/**
	 * Récupère les images pour le slider de la page d'accueil
	 * @return array
	 */
	private function getSliderImages(Packages $assetPackage): array {
	    $sliderImages = [];
	    
	    $categories = $this->getDoctrine()
	       ->getRepository(\MenuBundle\Entity\Categorie::class)
	       ->getSliderImages();
	    
	       if ($categories) {
	           $indice = 0;
	           foreach ($categories as $category) {
	               $content = $category->getContent();
	               
	               if (property_exists($content, "slide")) {
	                   $sliderImages[] = [
	                       "image" => $assetPackage->getUrl("images/" . $content->slide),
	                       "alt" => $content->title->fr,
	                       "caption" => $content->caption->fr,
	                       "active" => $indice === 0 ? true : false,
	                       "order" => $indice
	                   ];
	                   $indice++;
	               }
	           }
	       }
	       
	       return $sliderImages;
	}
	
	private function _topSales() {
	    $repository = $this->getDoctrine()
	    ->getManager()
	    ->getRepository(\MenuBundle\Entity\Categorie::class);
	    
	    $topSales = $this->getTopSelling($repository->getTopSelling());
	    
        return $topSales;
	}
	
	/**
	 * Retourne le top 10 des ventes
	 * @param $articles
	 * @return array
	 */
	private function getTopSelling(array $articles): array {
	    $soldedArticles = [];
	    
	    foreach ($articles as $article) {
	        $content = $article->getContent();
	        
	        if (property_exists($content, "sold")) {
	            $soldedArticles[] = [
	                "product" => $article->getRawContent(),
	                "soldQuantity" => $content->sold
	            ];
	        }
	    }
	    
	    // Tri le résultat dans l'odre des quantités vendues
	    uasort($soldedArticles, function($first, $second) {
	        return $first["soldQuantity"] <=> $second["soldQuantity"];
	    });
	        
	    // Récupère les 10 dernières meilleures ventes
	    return array_slice($soldedArticles, 0, 9, true);
	}
    
}