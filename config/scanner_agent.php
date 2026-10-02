<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Universal Windows Scanner Agent
    |--------------------------------------------------------------------------
    |
    | The private key NEVER goes inside the Windows Agent.
    | MikroPanel signs short-lived browser-to-agent authorization tokens.
    | The Windows Agent contains only the matching PUBLIC key.
    |
    */

    'private_key_path' => env(
        'SCANNER_AGENT_PRIVATE_KEY_PATH',
        '/etc/mikropanel/scanner-agent-private.pem',
    ),

    'token_ttl_seconds' => 300,

];
