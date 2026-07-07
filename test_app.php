<?php
require '/opt/mailpanel/vendor/autoload.php';
\MailPanel\Bootstrap\Environment::load('/opt/mailpanel');
try {
    $app = \MailPanel\Bootstrap\ApplicationFactory::create('/opt/mailpanel');
    $req = \MailPanel\Core\Request::createFromGlobals();
    $app->handle($req);
} catch (\Throwable $e) {
    echo $e->getMessage() . "\n" . $e->getTraceAsString();
}

