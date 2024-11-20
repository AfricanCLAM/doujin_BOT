<?php

namespace App;

use DOMDocument;
use DOMXPath;

function handleCommand(string $userMessage, string $fullName): string
{
    $replyMsg = "";

    // Check for "/code #code" using regex
    if (preg_match('/^\/code\s+#?(\d+)/i', $userMessage, $matches)) {
        $doujinId = $matches[1];
        return parseDoujinInfo($doujinId);
    }

    // Match basic commands
    switch ($userMessage) {
        case "/start":
            $replyMsg = "Hello, $fullName! 👋 Welcome to SALMAN BOT! 
                \n /code #code: Get doujinshi (nhentai) information
                \n /ping: Ping the bot
                \n /help: Get help using the bot";
            break;

        case "/ping":
            $replyMsg = "Pong! 🏓 The bot is functioning normally.";
            break;

        case "/help":
            $replyMsg = "
                \n /code #code: Get doujinshi (nhentai) information
                \n /ping: Ping the bot
                \n /help: Get help using the bot";
            break;

        default:
            $replyMsg = "I don't recognize that command. Type /help for available commands.";
            break;
    }

    return $replyMsg;
}

function parseDoujinInfo(string $doujinId): string
{
    $url = "https://nhentai.net/g/$doujinId/";

    try {
        $html = fetchHTML($url);
        if (!$html) {
            return "Failed to fetch data. Please check the code or try again later.";
        }

        // Parse the HTML using DOMDocument and DOMXPath
        $dom = new DOMDocument();
        @$dom->loadHTML($html); // Suppress warnings for malformed HTML

        $xpath = new DOMXPath($dom);

        // Extract title
        $titleNode = $xpath->query("//h1[@class='title']")->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : "Unknown Title";

        // Extract code
        $codeNode = $xpath->query("//h3[@id='gallery_id']/span[@class='hash']")->item(0);
        $code = $codeNode ? trim($codeNode->nextSibling->textContent) : "Unknown Code";

        // Extract tags (from meta description)
        $tagsNode = $xpath->query("//meta[@name='twitter:description']")->item(0);
        $tagsContent = $tagsNode ? $tagsNode->getAttribute('content') : "No Tags Available";

        // Return the formatted response
        return "<b>Title:</b> $title\n<b>Tags:</b> $tagsContent\n<b>Code:</b> $code\n\n<b><a href=\"$url\">LINK</a></b>\n";
    } catch (\Exception $e) {
        // Log exception (use a logger in a real application)
        file_put_contents(__DIR__ . '/log/error.log', $e->getMessage() . "\n", FILE_APPEND);
        return "An error occurred while fetching the data. Please try again later.";
    }
}

function fetchHTML(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36");

    $html = curl_exec($ch);

    if (curl_errno($ch)) {
        // Log cURL error
        file_put_contents(__DIR__ . '/log/curl_error.log', curl_error($ch) . "\n", FILE_APPEND);
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    return $html;
}
