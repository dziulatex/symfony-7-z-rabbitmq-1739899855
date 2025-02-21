<?php

namespace App\Tests\Trait;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;


trait TestUtilsTrait
{

    /**
     * @param string[] $fixtures
     * @return ReferenceRepository
     */
    protected function loadFixtures(array $fixtures): ReferenceRepository
    {
        $databaseTool = self::getContainer()->get(DatabaseToolCollection::class)->get();
        return $databaseTool->loadFixtures($fixtures)->getReferenceRepository();
    }

}
