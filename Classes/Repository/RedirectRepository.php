<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Repository;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlInfo;
use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlResult;
use GeorgRinger\RedirectGenerator\Exception\DuplicateException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RedirectRepository
{
    private const CUSTOM_USER_ID = 19191918;

    private const TABLE = 'sys_redirect';

    public function getRedirect(string $url): ?array
    {
        $urlInfo = GeneralUtility::makeInstance(UrlInfo::class, $url);

        $queryBuilder = $this->getConnection()->createQueryBuilder();

        $row = $queryBuilder->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('createdby', $queryBuilder->createNamedParameter(self::CUSTOM_USER_ID, \PDO::PARAM_INT)),
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter('*', \PDO::PARAM_STR)),
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($urlInfo->getHost(), \PDO::PARAM_STR))
                ),
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($urlInfo->getPathWithQuery(), \PDO::PARAM_STR))
            )
            ->execute()
            ->fetch();

        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * @param string $url
     * @param string $target
     * @param Configuration $configuration
     * @param bool $dryRun
     * @throws DuplicateException
     */
    public function addRedirect(string $url, string $target, Configuration $configuration, bool $dryRun = false): void
    {
        $existingRow = $this->getRedirect($url);
        if (is_array($existingRow)) {
            throw new DuplicateException(sprintf('Redirect for "%s" exists already with ID %s!', $url, $existingRow['uid']), 1568487151);
        }

        if ($dryRun) {
            return;
        }

        $urlInfo = GeneralUtility::makeInstance(UrlInfo::class, $url);
        $connection = $this->getConnection();

        $data = [
            'createdby' => self::CUSTOM_USER_ID,
            'createdon' => $GLOBALS['EXEC_TIME'],
            'updatedon' => $GLOBALS['EXEC_TIME'],
            'keep_query_parameters' => $configuration->getKeepQueryParameters() ? 1 : 0,
            'is_regexp' => $configuration->getRegexp() ? 1 : 0,
            'force_https' => $configuration->getForceHttps() ? 1 : 0,
            'target_statuscode' => $configuration->getTargetStatusCode(),
            'disable_hitcount' => $configuration->getDisableHitCount() ? 1 : 0,
            'respect_query_parameters' => $configuration->getRespectQueryParameters() ? 1 : 0,
            'source_host' => $urlInfo->getHost() ?: '*',
            'source_path' => $urlInfo->getPathWithQuery(),
            'target' => $target
        ];
        $connection->insert(self::TABLE, $data);
    }

    public function getAllRedirects(): array
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->execute()
            ->fetchAll();
    }

    private function getConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE);
    }
}
