<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-plateforme-numerique.
 *
 * Copyright (c) TheBeardedBear and the urssaf-plateforme-numerique contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafPlateformeNumerique\Infrastructure\Transaction;

use TheBeardedBear\UrssafPlateformeNumerique\Application\Port\TransactionManager;

/**
 * Transaction port for stores without transactions: runs the operation immediately, without any rollback.
 */
final class ImmediateTransactionManager implements TransactionManager
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        return $operation();
    }
}
