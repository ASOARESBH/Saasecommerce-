<?php
require_once __DIR__ . '/../app/autoload.php';
use App\Core\Crypto;

$_ENV['APP_SECRET'] = 'smoke-test-secret-with-more-than-16-chars';
$plain = 'integration-secret';
$encrypted = Crypto::encrypt($plain);
if (Crypto::decrypt($encrypted) !== $plain) throw new RuntimeException('Crypto round-trip falhou.');
if ($encrypted === $plain) throw new RuntimeException('Segredo não foi cifrado.');

echo "Smoke tests passed\n";
