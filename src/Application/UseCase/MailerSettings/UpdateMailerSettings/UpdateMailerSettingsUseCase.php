<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\MailerSettings\UpdateMailerSettings;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerSettingsRepositoryInterface;
use Rez\Domain\Mailer\MailerSettings;

final class UpdateMailerSettingsUseCase implements UpdateMailerSettingsUseCaseInterface
{
    public function __construct(
        private readonly MailerSettingsRepositoryInterface $mailerSettingsRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(UpdateMailerSettingsRequest $request): UpdateMailerSettingsResponse
    {
        try {
            $existing = $this->mailerSettingsRepository->get();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load mailer settings.', 0, $e);
        }

        $updated = new MailerSettings(
            $request->fromAddress ?? $existing->fromAddress,
            $request->fromName ?? $existing->fromName,
        );

        try {
            $this->mailerSettingsRepository->update($updated);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to save mailer settings.', 0, $e);
        }

        return new UpdateMailerSettingsResponse($updated);
    }
}
