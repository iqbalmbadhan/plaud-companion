<?php

namespace App\Services;

use RuntimeException;

/** Thrown when a backup cannot be decrypted — wrong passphrase or a tampered/corrupt file. */
class BackupPassphraseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The passphrase is incorrect, or the backup file is damaged.');
    }
}
