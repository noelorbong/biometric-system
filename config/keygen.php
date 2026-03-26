<?php

return [
    'account_id'    => env('KEYGEN_ACCOUNT_ID', 'e13ac3f8-8ffd-439e-beae-1c056e102ed2'),
    'product_id'    => env('KEYGEN_PRODUCT_ID', '08a1196f-d8cf-4961-aca3-37557f734a36'),
    'product_token' => env('KEYGEN_PRODUCT_TOKEN', 'prod-632b161fcfb77f3459d559cba846944b0944a6d29448817dc81e024a039152cbv3'),
    'trial_days'    => (int) env('KEYGEN_TRIAL_DAYS', 7),
];
