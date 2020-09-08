<?php

namespace Wasinger\MetaformBundle\Repository;

use Wasinger\MetaformBundle\Entity\FormData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FormData|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormData|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormData[]    findAll()
 * @method FormData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormData::class);
    }
}
