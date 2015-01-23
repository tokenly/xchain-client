<?php

return [

    'connection_url' => getenv('XCHAIN_CONNECTION_URL') ?: 'http://xchain.tokenly.co',
    'api_token'      => getenv('XCHAIN_API_TOKEN')      ?: null,
    'api_key'        => getenv('XCHAIN_API_KEY')        ?: null,

];

