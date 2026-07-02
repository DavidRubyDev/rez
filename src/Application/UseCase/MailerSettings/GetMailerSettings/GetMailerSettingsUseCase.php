<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\MailerSettings\GetMailerSettings;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\MailerSettingsRepositoryInterface;

final class GetMailerSettingsUseCase implements GetMailerSettingsUseCaseInterface
{
    public function __construct(
        private readonly MailerSettingsRepositoryInterface $mailerSettingsRepository,
    ) {
    }

    /** @throws DatabaseException */
    public function execute(GetMailerSettingsRequest $request): GetMailerSettingsResponse
    {
        try {
            $settings = $this->mailerSettingsRepository->get();
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load mailer settings.', 0, $e);
        }

        return new GetMailerSettingsResponse($settings);
    }
}
