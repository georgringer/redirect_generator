<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Repository;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlInfo;
use GeorgRinger\RedirectGenerator\Event\AfterRedirectAddedEvent;
use GeorgRinger\RedirectGenerator\Event\BeforeRedirectAddedEvent;
use GeorgRinger\RedirectGenerator\Exception\ConflictingDuplicateException;
use GeorgRinger\RedirectGenerator\Exception\NonConflictingDuplicateException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

class RedirectRepository
{
    private const TABLE = 'sys_redirect';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function getRedirect(string $url): ?array
    {
        $urlInfo = new UrlInfo($url);

        $queryBuilder = $this->getConnection()->createQueryBuilder();

        $row = $queryBuilder->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter('*', Connection::PARAM_STR)),
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($urlInfo->host, Connection::PARAM_STR))
                ),
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($urlInfo->getPathWithQuery(), Connection::PARAM_STR))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: null;
    }

    /**
     * @throws ConflictingDuplicateException
     * @throws NonConflictingDuplicateException
     */
    public function addRedirect(string $url, string $target, Configuration $configuration, bool $dryRun = false): void
    {
        $existingRow = $this->getRedirect($url);
        if (is_array($existingRow)) {
            if ($target !== $existingRow['target']) {
                throw new ConflictingDuplicateException(
                    sprintf(
                        'Redirect for "%s" exists already with ID %s! Existing target is "%s", new target would be "%s".',
                        $url,
                        $existingRow['uid'],
                        $existingRow['target'],
                        $target
                    ),
                    1568487151
                );
            }
            throw new NonConflictingDuplicateException(
                sprintf(
                    'Redirect for "%s" exists already with ID %s, but has the same target as the new redirect.',
                    $url,
                    $existingRow['uid'],
                ),
                1568487151
            );
        }

        if ($dryRun) {
            return;
        }

        $event = $this->eventDispatcher->dispatch(new BeforeRedirectAddedEvent($url, $target, $configuration));
        $url = $event->getSourceUrl();
        $target = $event->getTargetUrl();
        $configuration = $event->getConfiguration();

        $urlInfo = new UrlInfo($url);
        $connection = $this->getConnection();

        $connection->insert(self::TABLE, [
            'creation_type'          => 6332,
            'createdon'              => $GLOBALS['EXEC_TIME'],
            'updatedon'              => $GLOBALS['EXEC_TIME'],
            'keep_query_parameters'  => $configuration->keepQueryParameters ? 1 : 0,
            'is_regexp'              => $configuration->isRegexp ? 1 : 0,
            'force_https'            => $configuration->forceHttps ? 1 : 0,
            'target_statuscode'      => $configuration->targetStatusCode,
            'disable_hitcount'       => $configuration->disableHitCount ? 1 : 0,
            'respect_query_parameters' => $configuration->respectQueryParameters ? 1 : 0,
            'source_host'            => $urlInfo->host ?: '*',
            'source_path'            => $urlInfo->getPathWithQuery(),
            'target'                 => $target,
            'integrity_status'       => \TYPO3\CMS\Redirects\Utility\RedirectConflict::NO_CONFLICT,
        ]);

        $this->eventDispatcher->dispatch(new AfterRedirectAddedEvent(
            $url,
            $target,
            $configuration,
            (int)$connection->lastInsertId(),
        ));
    }

    public function getAllRedirects(?string $redirectType = null, ?int $creationType = null): array
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $queryBuilder->select('*')->from(self::TABLE);

        if ($redirectType !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('redirect_type', $queryBuilder->createNamedParameter($redirectType, Connection::PARAM_STR))
            );
        }
        if ($creationType !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('creation_type', $queryBuilder->createNamedParameter($creationType, Connection::PARAM_INT))
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function getDistinctColumnValues(string $column): array
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        return array_column(
            $queryBuilder
                ->select($column)
                ->from(self::TABLE)
                ->groupBy($column)
                ->orderBy($column)
                ->executeQuery()
                ->fetchAllAssociative(),
            $column
        );
    }

    private function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }
}
