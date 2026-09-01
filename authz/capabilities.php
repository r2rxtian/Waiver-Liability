<?php
declare(strict_types=1);

const MW_CAPABILITIES = [
    'SYSTEM ADMIN'=>['*'],
    'CLINIC NURSE'=>['visits.manage','waivers.facilitate'],
    'SUPERVISOR'=>['waivers.acknowledge'],
    'HR / CLINIC ADMIN'=>['waivers.report','templates.manage'],
    'EMPLOYEE'=>['waivers.sign-own'],
];

function can(string $role, string $capability): bool
{
    $grants=MW_CAPABILITIES[$role]??[];
    return in_array('*',$grants,true)||in_array($capability,$grants,true);
}
