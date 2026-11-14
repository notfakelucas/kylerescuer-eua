<?php
$defaultKey = base64_decode('cmtfbGl2ZV81MVVEZzVPR2NZeEp2Q3ExOVl4a1RKbWVUck5FOGxZcEMxSHVRdkZZMzZScUd2VVMzRWFhREJrbm5WUWxveGZKcTFQNDVpa1gyeGp0ZERwS0s4TVdaV2lQdDAwbWdxNU1ISWI=');
define('STRIPE_SECRET_KEY',     getenv('STRIPE_SECRET_KEY') ?: $defaultKey);
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_JgTTr8BUaeLyLLiV24aom8SOrdVlBfuL');
define('STRIPE_API_BASE',       'https://api.stripe.com/v1');
