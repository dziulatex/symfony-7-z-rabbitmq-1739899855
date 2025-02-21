<?php

namespace App\TestFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ClientFixture extends Fixture implements FixtureGroupInterface
{
    public function __construct()
    {
    }

    public function load(ObjectManager $manager): void
    {
        $client = new Client(1);
        $client->setCity('xd');
        $client->setEmail('test@test.com');
        $client->setFullName('xdddd');

        $manager->persist($client);
        $manager->flush();
        $this->setReference('client1', $client);
    }

    public static function getGroups(): array
    {
        return ['test'];
    }
}