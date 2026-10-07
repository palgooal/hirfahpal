<?php

namespace App\Support\Inventory;

use RuntimeException;

/**
 * A vendor order's reserved stock could not be committed; the caller's
 * transaction must roll back (VEN-BE-019).
 */
class StockCommitmentException extends RuntimeException {}
