<?php
declare(strict_types=1);

function secure_password_hash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function secure_password_verify(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}
