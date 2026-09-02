<?php
declare(strict_types=1);

// Reuse the company's existing, centrally managed SQL Server configuration.
// Credentials remain in QRS_new/conn/config.php and are never duplicated here.
require_once __DIR__.'/config.php';

function database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) return $connection;
    try {
        // Match the proven QRS_new SQLSRV connection string. Some deployed
        // ODBC 17 clients reject an explicit Encrypt=no option.
        $dsn = 'sqlsrv:Server='.QRS_DB_HOST.';Database='.QRS_DB_NAME.';TrustServerCertificate=true';
        $connection = new PDO($dsn, QRS_DB_USER, QRS_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
        ]);
        return $connection;
    } catch (PDOException $exception) {
        error_log('Medical waiver database connection failed: '.$exception->getMessage());
        throw new RuntimeException('The database is temporarily unavailable.');
    }
}
