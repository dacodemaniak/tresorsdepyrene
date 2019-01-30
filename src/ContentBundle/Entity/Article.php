<?php
/**
 * @name Article Entité sur les articles
 * @version 1.0.0
 * @version 1.0.1
 *  - Ajout du prix TTC dans la méthode getRawContent()
 */
namespace ContentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

/**
 * Article
 *
 * @ORM\Table(name="article")
 * @ORM\Entity(repositoryClass="ContentBundle\Repository\ArticleRepository")
 */
class Article
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=75, unique=true)
     */
    private $slug;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_enabled", type="boolean")
     */
    private $isEnabled;

    /**
     * @var string
     *
     * @ORM\Column(name="content", type="text", nullable=true)
     */
    private $content;
    
    /**
     * @ORM\OneToMany(targetEntity=DecorArticle::class, mappedBy="article")
     */
    private $decors;

    
    /**
     * @ORM\OneToMany(targetEntity=CategorieToArticles::class, mappedBy="article")
     * 
     * @var ArrayCollection
     */
    private $categorieToArticles;
    

    
    public function __construct() {
        
    	$this->decors = new ArrayCollection();
    	$this->categorieToArticles = new ArrayCollection();
    }
    
    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set slug
     *
     * @param string $slug
     *
     * @return Article
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set isEnabled
     *
     * @param boolean $isEnabled
     *
     * @return Article
     */
    public function setIsEnabled($isEnabled)
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    /**
     * Get isEnabled
     *
     * @return bool
     */
    public function getIsEnabled()
    {
        return $this->isEnabled;
    }

    /**
     * Set content
     *
     * @param string $content
     *
     * @return Article
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content
     *
     * @return Object | Array
     */
    public function getContent()
    {
    	
    	if ($this->content) {
    		$json = new JsonDecode();
    		return $json->decode($this->content, JsonEncoder::FORMAT);
    	}
    }
    
    /**
     * Retourne le contenu de l'article sous forme de tableau
     * @return array
     */
    public function getRawContent(): array {
        if ($this->content) {
            
            $json = new JsonDecode(true);
            
            $content = $json->decode($this->content, JsonEncoder::FORMAT);
            
            // Recalcul les prix TTC
            for($i = 0; $i <count($content["pricing"]); $i++) {
                $pricing = $content["pricing"][$i];
                $ttc = $pricing["ht"] * (1 + $content["vat"]);
                $pricing["ttc"] = round($ttc, 2);
                // Remplace le prix...
                $content["pricing"][$i] = $pricing;
            }
            
            return $content;
        }
        
        return [];
    }
    
    /**
     * Retourne la liste des décorations de l'article
     * @return ArrayCollection
     */
    public function getDecors() {
    	return $this->decors;
    }
    
    /**
     * Retourne les décorateurs de l'article courant
     * @return array
     */
    public function getDecorators(): array {
        $decorators = [];
        
        foreach($this->decors as $toDecors) {
            $decorators[] = [
                "decorator" => $toDecors->getDecor()->getRawContent(),
                "values" => $toDecors->getRawContent()
            ];
        }
        
        return $decorators;
    }
    
    /**
     * Extrait le prix le plus bas du produit courant
     * @return number
     */
    public function getSmallestPrice(): array {
        $smallestPrice = [];
        
        if (($content = $this->getContent()) !== null) {
            
            if ((float) $content->vat = 0.05) {
                $content->vat = 0.055;
            }
            
            $pricing = $content->pricing;
            
            if (is_array($pricing) && count($pricing) > 1) {
                usort(
                    $pricing,
                    function ($price1, $price2) {
                        return $price1->ht <=> $price2->ht;
                    }
                );
                $price = array_shift($pricing);


                $smallestPrice["ttc"] = $price->ht * (1 + $content->vat);
                $smallestPrice["multi"] = true;
            } else {
                $price = $pricing[0];
                $smallestPrice["ttc"] = $price->ht * (1 + $content->vat);
                $smallestPrice["multi"] = false;
            }
        }
        
        return $smallestPrice;
    }
    
    /**
     * Retourne la quantité maximum par commande pour ce produit
     * @return int
     */
    public function getMaxPerOrder(): int {
        $maxPerOrder = 0;
        
        if (($content = $this->getContent()) !== null) {
            $pricing = $content->pricing;
            
            usort(
                $pricing,
                function ($price1, $price2) {
                    return $price1->ht <=> $price2->ht;
                }
            );
            
            if (count($pricing) > 1) {
                $minPrice = array_shift($pricing);
            } else {
                $minPrice = $pricing[0];
            }
            
            if (($minPrice->stock - $minPrice->thresold) < $minPrice->maxPerOrder) {
                $maxPerOrder = $minPrice->stock - $minPrice->thresold;
            } else {
                $maxPerOrder = $minPrice->thresold;
            }
        }
        
        return $maxPerOrder;
    }
    
    
    /**
     * Retourne la liste des prix des produits dans l'ordre croissant
     * @return array
     */
    public function getPrices(): array {
        if (($content = $this->getContent()) !== null) {
            $pricing = $content->pricing;
            
            usort(
                $pricing,
                function ($price1, $price2) {
                    return $price1->ht <=> $price2->ht;
                }
            );
            
            return $pricing;
        }
        
        return [];
    }
    
    /**
     * Retourne l'image principale du produit
     * @return array
     */
    public function getMainImage() {
        $images = [];
        $imagePath = "";

       $package = new Package(new EmptyVersionStrategy());
        
       $noImage =  [
            "src" => $package->getUrl("/images/no-image-icon.png"), // $assetPackage->getUrl("images/" . $content->slide),
            "alt" => $this->getTitleFr() . " pas encore d'image"
        ];
       
        foreach ($this->decors as $toDecors) {
            if ($toDecors->getDecor()->getSlug() === "images-produits") {
                $content = $toDecors->getDecor()->getContent();
                $imagePath = $content->root;
                $values = $toDecors->getContent();
                $images[] = $values;
            }
        }
        
        //echo "Images : ";
        //var_dump($images);
        
        if (is_array($images)) {
            $content = array_pop($images);
            if (is_array($content)) {
                $image = array_pop($content);
            } else {
                return $noImage;
            }
        } else {
            return $noImage;
        }
        
        
        //echo "Image récupérée : " . $this->getId();
        //var_dump($image);
        
        
        if ($image) {
            if (property_exists($image, "src")) {
                if (file_exists($imagePath . $image->src)){
                    return [
                        "src" => $imagePath . $image->src,
                        "alt" => $image->alt->fr
                    ];
                }
            }
        }
        
        return $noImage;
  

    }
    
    /**
     * Récupère les images du produit
     * @return array
     */
    public function getImages(): array {
        $images = [];
        $imagePath = "";
        
        $sliderImages = [];
        
        foreach ($this->decors as $toDecors) {
            if ($toDecors->getDecor()->getSlug() === "images-produits") {
                $content = $toDecors->getDecor()->getContent();
                $imagePath = $content->root;
                $values = $toDecors->getContent();
                $images[] = $values;
            }
        }
        
        if (count($images)) {
            $indice = 0;
            foreach ($images as $image) {
                foreach ($image as $object) {
                    if (property_exists($object, "src")) {
                        $sliderImages[] = [
                            "image" => $imagePath . $object->src,
                            "alt" => $object->alt->fr,
                            "order" => $indice,
                            "active" => $indice === 0 ? true : false
                        ];
                        $indice++;
                    }
                }
            }
            if (count($sliderImages)) {
                return $sliderImages;
            }
            
        }
        
        $package = new Package(new EmptyVersionStrategy());
            
        return [
            
            [
                "image" => $package->getUrl("/images/no-image-icon.png"),
                "alt" => $this->getTitleFr() . " pas encore d'image",
                "order" => 0,
                "active" => true
            ]
        ];
        
    }
    
    /**
     * Retourne l'ensemble des décorateurs du produit courant
     * @return array
     */
    public function getProductDecorators(): array {
        $decors = [];
        
        foreach ($this->decors as $toDecors) {
            if ($toDecors->getDecor()->getSlug() !== "images-produits" && $toDecors->getDecor()->getSlug() !== "images") {
                $decors[] = [
                    "title" => $toDecors->getDecor()->getTitleFr(),
                    "icon" => $toDecors->getDecor()->getIcon(),
                    "content" => $toDecors->getFr(),
                    "isIconic" => $toDecors->getDecor()->getIconic()
                ];
            }
        }
        return $decors;
    }
    
    /**
     * Ajoute un élément de décoration à l'article courant
     * @param Decor $decor
     */
    public function addDecor(Decor $decor) {
    	$this->decors[] = $decor;
    }
    
    /**
     * Méthode magique pour la récupération d'un contenu spécifique
     * dans le contenu JSON d'un document
     * @param string $methodName
     * @param array $args
     */
    public function __call(string $methodName, array $args) {
    	if (($content = $this->getContent()) !== null) {
    		preg_match_all('/((?:^|[A-Z])[a-z]+)/', $methodName, $methodComposition);
    		
    		$composition = $methodComposition[0];
    		
    		$isGetter = ($composition[0] === "get") ? true : false;
    		$hasLang = (count($composition) === 3) ? true : false;
    		
    		$property = strtolower($composition[1]);
    		
    		if ($isGetter) {
    		    if (property_exists($content, $property) && $content->{$property} !== null) {
	    			if ($hasLang) {
	    				if (property_exists($content->{$property}, strtolower($composition[2]))) {
	    					return $content->{$property}->{strtolower($composition[2])};
	    				}
	    			} else {
	    				return $content->{$property};
	    			}
	    		}
    		}
    	}
    	return "";
    }
}

