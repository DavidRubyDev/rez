<?php

declare(strict_types=1);

namespace Rez\Handler\Availability;

use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleRequest;
use Rez\Application\UseCase\Availability\SaveAvailabilityRule\SaveAvailabilityRuleUseCaseInterface;
use Rez\Domain\Resource\ResourceId;
use Rez\Infrastructure\Mapper\DayOfWeekMapper;

final class SaveAvailabilityRuleHandler
{
    private readonly DayOfWeekMapper $dayMapper;

    public function __construct(
        private readonly SaveAvailabilityRuleUseCaseInterface $useCase,
    ) {
        $this->dayMapper = new DayOfWeekMapper();
    }

    /**
     * @param array{resource_id: string, day_of_week: string, open_time: string, close_time: string} $data
     * @return array{resource_id: string, day_of_week: string, open_time: string, close_time: string}
     */
    public function handle(array $data): array
    {
        $response = $this->useCase->execute(new SaveAvailabilityRuleRequest(
            ResourceId::fromString($data['resource_id']),
            $this->dayMapper->fromString($data['day_of_week']),
            $data['open_time'],
            $data['close_time'],
        ));

        $rule = $response->rule;

        return [
            'resource_id' => $rule->resourceId->toString(),
            'day_of_week' => $this->dayMapper->toString($rule->dayOfWeek),
            'open_time'   => $rule->openTime,
            'close_time'  => $rule->closeTime,
        ];
    }
}
