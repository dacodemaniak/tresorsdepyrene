<?php

namespace ContentBundle\Repository;

/**
 * ArticleRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ArticleRepository extends \Doctrine\ORM\EntityRepository {
    
    public function fullContentSearch(string $terms) {
        
        
        return $this->createQueryBuilder('p')
            ->addSelect("MATCH_AGAINST (p.content, :terms 'IN NATURAL MODE') as score")
            ->add('where', 'MATCH_AGAINST(p.content, :terms) > 0.8')
            ->setParameter('terms', $terms)
            ->orderBy('score', 'desc')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }
}
