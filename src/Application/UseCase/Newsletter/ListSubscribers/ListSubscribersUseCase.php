<?php

declare(strict_types=1);

namespace Rez\Application\UseCase\Newsletter\ListSubscribers;

use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\NewsletterRepositoryInterface;
use Rez\Application\Validation\ListParamsValidator;

final class ListSubscribersUseCase implements ListSubscribersUseCaseInterface
{
    private const SORTABLE = ['email', 'name', 'source', 'opted_in_at'];

    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletterRepository,
    ) {
    }

    /**
     * @throws DatabaseException
     * @throws \InvalidArgumentException
     */
    public function execute(ListSubscribersRequest $request): ListSubscribersResponse
    {
        ListParamsValidator::validate($request->offset, $request->limit, $request->sortBy, $request->sortDir, self::SORTABLE);

        try {
            $subscribers = $this->newsletterRepository->findPage(
                search: $request->search,
                source: $request->source,
                offset: $request->offset,
                limit: $request->limit,
                sortBy: $request->sortBy,
                sortDir: $request->sortDir,
            );
            $total = $this->newsletterRepository->countPage($request->search, $request->source);
        } catch (DatabaseException $e) {
            throw new DatabaseException('Failed to load newsletter subscribers.', 0, $e);
        }

        return new ListSubscribersResponse($subscribers, $total);
    }
}
