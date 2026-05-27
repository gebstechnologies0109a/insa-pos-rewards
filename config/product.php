<?php

return [
    'name' => env('APP_PRODUCT', 'auto'), // insa | epayplus | auto
    'epayplus_hosts' => array_filter(array_map('trim', explode(',', env('EPAYPLUS_HOSTS', 'epayplus.diybizrewards.com,localhost')))),
    'insa_hosts' => array_filter(array_map('trim', explode(',', env('INSA_HOSTS', 'insapos.diybizrewards.com')))),
    'insa_pos_cashier_url' => env('INSA_POS_CASHIER_URL', 'https://insapos.diybizrewards.com/pos/cashier'),
];
