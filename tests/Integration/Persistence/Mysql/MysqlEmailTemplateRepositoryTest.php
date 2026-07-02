<?php

declare(strict_types=1);

namespace Rez\Tests\Integration\Persistence\Mysql;

use Psr\Log\NullLogger;
use Rez\Domain\Exception\EmailTemplateNotFoundException;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;
use Rez\Infrastructure\Persistence\Mysql\MysqlEmailTemplateRepository;

class MysqlEmailTemplateRepositoryTest extends MysqlIntegrationTestCase
{
    private MysqlEmailTemplateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MysqlEmailTemplateRepository($this->pdo(), new NullLogger());
    }

    private function makeTemplate(): EmailTemplate
    {
        return EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>');
    }

    public function testSaveAndFindByIdRoundtrip(): void
    {
        $template = $this->makeTemplate();
        $this->repository->save($template);

        $found = $this->repository->findById($template->id);

        $this->assertTrue($template->id->equals($found->id));
        $this->assertSame('Welcome', $found->subject);
        $this->assertSame('<p>Hello</p>', $found->html);
        // DATETIME columns truncate to whole seconds, so compare at that precision rather
        // than full DateTimeImmutable equality (which would fail on microseconds).
        $this->assertSame($template->createdAt->format('Y-m-d H:i:s'), $found->createdAt->format('Y-m-d H:i:s'));
    }

    public function testFindByIdMissingThrowsEmailTemplateNotFoundException(): void
    {
        $this->expectException(EmailTemplateNotFoundException::class);
        $this->repository->findById(EmailTemplateId::generate());
    }

    public function testFindAllReturnsAllSavedTemplates(): void
    {
        $this->repository->save($this->makeTemplate());
        $this->repository->save($this->makeTemplate());

        $result = $this->repository->findAll();

        $this->assertCount(2, $result);
    }

    public function testSaveUpsertsExistingTemplate(): void
    {
        $template = $this->makeTemplate();
        $this->repository->save($template);

        $updated = $template->withContent('New Subject', '<p>Updated</p>');
        $this->repository->save($updated);

        $found = $this->repository->findById($template->id);
        $this->assertSame('New Subject', $found->subject);
        $this->assertSame('<p>Updated</p>', $found->html);
        $this->assertCount(1, $this->repository->findAll());
    }

    public function testDeleteRemovesTemplate(): void
    {
        $template = $this->makeTemplate();
        $this->repository->save($template);
        $this->repository->delete($template->id);

        $this->expectException(EmailTemplateNotFoundException::class);
        $this->repository->findById($template->id);
    }
}
