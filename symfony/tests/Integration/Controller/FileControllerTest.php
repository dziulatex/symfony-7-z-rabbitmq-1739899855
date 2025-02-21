<?php

namespace App\Tests\Integration\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class FileControllerTest extends WebTestCase
{
    private const TEST_EMAIL = 'test@example.com';
    private const TEST_PASSWORD = 'test123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->container = static::getContainer();
        $this->entityManager = $this->container->get(EntityManagerInterface::class);

        // Create test user
        $this->createTestUser();

        // Log in
        $this->client->loginUser($this->getTestUser());
    }

    private function createTestUser(): void
    {
        // Only create if doesn't exist
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
        if ($existingUser) {
            return;
        }

        $user = new User();
        $user->setEmail(self::TEST_EMAIL);

        $passwordHasher = $this->container->get(UserPasswordHasherInterface::class);
        $hashedPassword = $passwordHasher->hashPassword($user, self::TEST_PASSWORD);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function getTestUser(): User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::TEST_EMAIL]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testUploadFormDisplays(): void
    {
        $this->client->request('GET', '/file/upload');
        static::assertResponseIsSuccessful();
        static::assertSelectorExists('form[name="file_upload"]');
    }

    public function testSuccessfulFileUpload(): void
    {
        // First get the form and debug its structure
        $crawler = $this->client->request('GET', '/file/upload');

        // Let's inspect the form structure
        $form = $crawler->filter('form[name="file_upload"]')->form();

        // Create a test file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'test.csv',
            'text/csv',
            null,
            true
        );

        // Attach file to form using the correct field name
        $form['file_upload[file]']->upload($uploadedFile);

        // Submit the form directly
        $this->client->submit($form);

        self::assertResponseRedirects('/file/upload');

        // Clean up
        unlink($tempFile);
    }
}