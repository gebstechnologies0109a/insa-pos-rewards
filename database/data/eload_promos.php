<?php

/**
 * E-Load promo SKUs aligned with DaFox /portal promos style.
 * Keys: provider code => list of [slug, display name, amount, validity_days?, description?]
 *
 * @return array<string, list<array{0: string, 1: string, 2: float, 3?: int, 4?: string}>>
 */
return [
    'GLOBE' => [
        ['GO50', 'Globe GO50', 50, 3, 'Unli all-net calls + texts, 500MB data (3 days)'],
        ['GO90', 'Globe GO90', 90, 7, 'Unli all-net calls + texts, 2GB data (7 days)'],
        ['GO120', 'Globe GO120', 120, 7, 'Globe GO120 promo'],
        ['GOSURF50', 'Globe GoSURF50', 50, 3, '2GB data + unli texts (3 days)'],
        ['GOSURF99', 'Globe GoSURF99', 99, 7, '5GB data + unli texts (7 days)'],
        ['GOSURF299', 'Globe GoSURF299', 299, 30, '20GB data + unli texts (30 days)'],
    ],
    'SMART' => [
        ['GIGA50', 'Smart GIGA50', 50, 3, '5GB data + unli texts (3 days)'],
        ['GIGA75', 'Smart GIGA75', 75, 5, 'Smart GIGA75 promo'],
        ['GIGA99', 'Smart GIGA99', 99, 7, '8GB data + unli texts (7 days)'],
        ['GIGA299', 'Smart GIGA299', 299, 30, '24GB data + unli texts (30 days)'],
        ['UNLI5G99', 'Smart Unli 5G 99', 99, 7, 'Unli 5G/4G data (7 days)'],
        ['MAGIC150', 'Smart Magic Data 150', 150, 30, 'Magic Data 150'],
    ],
    'TNT' => [
        ['TNT50', 'TNT 50', 50, 3, '2GB data + unli TNT/Smart texts (3 days)'],
        ['TNT99', 'TNT 99', 99, 7, '6GB data + unli texts (7 days)'],
        ['SURFSAYA50', 'TNT SurfSaya 50', 50, 3, 'SurfSaya 50 promo'],
        ['SURFSAYA99', 'TNT SurfSaya 99', 99, 7, 'SurfSaya 99 promo'],
    ],
    'TM' => [
        ['TM50', 'TM 50', 50, 3, 'TM promo load 50'],
        ['EASY50', 'TM EasySurf 50', 50, 3, 'EasySurf 50'],
        ['ALLNET20', 'TM AllNet 20', 20, 1, 'Unli all-net texts (1 day)'],
    ],
    'DITO' => [
        ['DITO50', 'DITO Level-Up 50', 50, 3, 'DITO 50 promo'],
        ['DITO99', 'DITO Level-Up 99', 99, 7, 'DITO 99 promo'],
        ['LEVELUP149', 'DITO Level-Up 149', 149, 30, 'DITO 149 promo'],
        ['LEVELUP199', 'DITO Level-Up 199', 199, 30, 'DITO 199 promo'],
        ['LEVELUP299', 'DITO Level-Up 299', 299, 30, 'DITO 299 promo'],
    ],
    'GOMO' => [
        ['GOMO50', 'GOMO 50', 50, 30, 'GOMO data promo 50'],
        ['GOMO99', 'GOMO 99', 99, 30, 'GOMO data promo 99'],
        ['GOMO199', 'GOMO 199', 199, 30, 'GOMO data promo 199'],
    ],
    'SUN' => [
        ['SUN50', 'Sun 50 Promo', 50, 3, 'Sun prepaid promo'],
        ['SUN99', 'Sun 99 Promo', 99, 7, 'Sun prepaid promo'],
    ],
    'CIGNAL' => [
        ['CIGNAL99', 'Cignal Prepaid 99', 99, 7, 'Cignal prepaid promo'],
        ['CIGNAL199', 'Cignal Prepaid 199', 199, 30, 'Cignal prepaid promo'],
    ],
    'GSAT' => [
        ['GSAT99', 'GSAT 99', 99, 7, 'GSAT prepaid promo'],
    ],
    'SMARTBRO' => [
        ['SMARTBRO50', 'Smart Bro 50', 50, 3, 'Smart Bro data promo'],
        ['SMARTBRO99', 'Smart Bro 99', 99, 7, 'Smart Bro data promo'],
    ],
    'CHERRYPREPAID' => [
        ['CHERRY50', 'Cherry Prepaid 50', 50, 3, 'Cherry prepaid promo'],
    ],
    'GAMEPIN' => [
        ['STEAM100', 'Steam 100', 100, null, 'Game pin Steam 100'],
        ['MLBB50', 'Mobile Legends 50', 50, null, 'MLBB diamonds 50'],
    ],
    'KURYENTELOAD' => [
        ['KURYENTE50', 'Kuryente Load 50', 50, null, 'Kuryente prepaid 50'],
        ['KURYENTE100', 'Kuryente Load 100', 100, null, 'Kuryente prepaid 100'],
    ],
];
