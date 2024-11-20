<?php
use Dotenv\Dotenv;

function loadEnvironmentVariables(): void
{
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    // Validate required environment variables
    $required = ['BOT_TOKEN'];
    foreach ($required as $var) {
        if (!isset($_ENV[$var])) {
            throw new Exception("Missing required environment variable: $var");
        }
    }
}
