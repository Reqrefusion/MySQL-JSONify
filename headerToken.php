<?php
require(__DIR__ . '/Base62.php');
class headerToken
{
    public function verifyToken(string $public, string $providedToken, string $secretKey): bool
    {
        $verif_access_key = $public . ':';

        $res = Base62::encode(hash_hmac('md5', $verif_access_key, $secretKey));
        if (strcasecmp($res, $providedToken) === 0) {
            debug('accepted key ' . $res);
            return true;
        } else {
            debug("hash computed => " . $res);
            debug("hash given    => " . $providedToken);
        }
        return false;
    }
}