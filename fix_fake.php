<?php

$files = ['tests/Feature/GoogleAuthTokenTest.php', 'tests/Feature/GoogleAuthFlowTest.php'];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $content = preg_replace('/SocialiteUser::fake\(\[/', '(new SocialiteUser)->map([', $content);
    file_put_contents($file, $content);
}
