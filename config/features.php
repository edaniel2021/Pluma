<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration Disabled
    |--------------------------------------------------------------------------
    |
    | Equivalent to Postiz's DISALLOW_REGISTRATION env flag. Checked both by
    | config/fortify.php (to unregister the /register routes entirely) and
    | by the Socialite sign-up flow (to refuse auto-registering new users
    | via Google/GitHub) so both signup paths are gated consistently.
    |
    */

    'registration_disabled' => env('REGISTRATION_DISABLED', false),

];
