<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Mailer;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Mailer\EmailTemplate;
use Rez\Domain\Mailer\EmailTemplateId;

class EmailTemplateTest extends TestCase
{
    public function testCreateStoresValuesAndSetsCreatedAt(): void
    {
        $id       = EmailTemplateId::generate();
        $template = EmailTemplate::create($id, 'Welcome', '<p>Hello</p>');

        $this->assertTrue($id->equals($template->id));
        $this->assertSame('Welcome', $template->subject);
        $this->assertSame('<p>Hello</p>', $template->html);
        $this->assertInstanceOf(\DateTimeImmutable::class, $template->createdAt);
    }

    public function testCreateWithEmptySubjectThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailTemplate::create(EmailTemplateId::generate(), '', '<p>Hello</p>');
    }

    public function testCreateWithEmptyHtmlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '');
    }

    public function testReconstructDoesNotValidate(): void
    {
        $template = EmailTemplate::reconstruct(
            EmailTemplateId::generate(),
            'Welcome',
            '<p>Hello</p>',
            new \DateTimeImmutable('2024-01-01 00:00:00'),
        );

        $this->assertSame('Welcome', $template->subject);
    }

    public function testWithContentReturnsNewInstancePreservingIdAndCreatedAt(): void
    {
        $created = EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>');

        $updated = $created->withContent('New Subject', '<p>Updated</p>');

        $this->assertTrue($created->id->equals($updated->id));
        $this->assertSame($created->createdAt, $updated->createdAt);
        $this->assertSame('New Subject', $updated->subject);
        $this->assertSame('<p>Updated</p>', $updated->html);
        $this->assertSame('Welcome', $created->subject, 'original instance must be unchanged');
    }

    public function testWithContentEmptySubjectThrows(): void
    {
        $created = EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>');

        $this->expectException(\InvalidArgumentException::class);
        $created->withContent('', '<p>Updated</p>');
    }

    public function testWithContentEmptyHtmlThrows(): void
    {
        $created = EmailTemplate::create(EmailTemplateId::generate(), 'Welcome', '<p>Hello</p>');

        $this->expectException(\InvalidArgumentException::class);
        $created->withContent('New Subject', '');
    }
}
