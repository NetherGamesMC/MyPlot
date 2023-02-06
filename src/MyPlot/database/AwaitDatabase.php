<?php
declare(strict_types=1);

namespace MyPlot\database;

use Generator;
use poggit\libasynql\result\SqlChangeResult;
use poggit\libasynql\result\SqlInsertResult;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlThread;
use SOFe\AwaitGenerator\Await;

abstract class AwaitDatabase extends BaseDatabase
{
    public function asyncGeneric(string $queryName, array $args = []): Generator
    {
        $this->connector->executeGeneric($queryName, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public function asyncRawGeneric(string $queryName, array $args = []): Generator
    {
        $this->connector->executeImplRaw([$queryName], [$args], [SqlThread::MODE_GENERIC], yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public function asyncChange(string $queryName, array $args = []): Generator
    {
        $this->connector->executeChange($queryName, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public function asyncRawChange(string $queryName, array $args = []): Generator
    {
        $resolve = yield;
        $this->connector->executeImplRaw([$queryName], [$args], [SqlThread::MODE_CHANGE], static function (array $results) use ($resolve): void {
            /** @var SqlChangeResult $result */
            $result = $results[0];
            $resolve($result->getAffectedRows());
        },
            yield Await::REJECT
        );
        return yield Await::ONCE;
    }

    public function asyncInsert(string $queryName, array $args = []): Generator
    {
        $resolve = yield;
        $this->connector->executeInsert($queryName, $args, static function (SqlInsertResult $result) use ($resolve): void {
            $resolve($result->getInsertId(), $result->getAffectedRows());
        },
            yield Await::REJECT
        );
        return yield Await::ONCE;
    }

    public function asyncRawInsert(string $queryName, array $args = []): Generator
    {
        $resolve = yield;
        $this->connector->executeImplRaw([$queryName], [$args], [SqlThread::MODE_INSERT], static function (array $results) use ($resolve): void {
            /** @var SqlInsertResult $result */
            $result = $results[0];
            $resolve($result->getInsertId(), $result->getAffectedRows());
        },
            yield Await::REJECT
        );
        return yield Await::ONCE;
    }

    public function asyncSelect(string $queryName, array $args = []): Generator
    {
        $this->connector->executeSelect($queryName, $args, yield, yield Await::REJECT);
        return yield Await::ONCE;
    }

    public function asyncRawSelect(string $queryName, array $args = []): ?Generator
    {
        $this->connector->executeImplRaw([$queryName], [$args], [SqlThread::MODE_SELECT], yield, yield Await::REJECT);
        /** @var SqlSelectResult[] $results */
        $results = yield Await::ONCE;
        $result = $results[0];
        return $result->getRows();
    }
}
