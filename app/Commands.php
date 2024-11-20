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
        return parseDoujinInfo($doujinId, 'predetermined');
    }

    // Match basic commands
    switch ($userMessage) {
        case "/start":
            $replyMsg = "Konnichiwa, $fullName! 👋 Welcome to Doujinshi BOT! 
                \n /code #code: Get doujinshi (nhentai) information
                \n /ping: Ping the bot
                \n /random: get random doujinshi
                \n /help: get help using the bot
                \n /list: Get list of the bot command";
            break;

        case "/ping":
            $replyMsg = "Pong! 🏓 The bot is functioning normally.";
            break;

        case "/random":
            $replyMsg = parseDoujinInfo(random(), "randomized");
            break;

        case "/help":
            $replyMsg = "format for code is #[6 digit num]\n\n/code ##539702 ❌\n/code 539702 ⭕\n/code #539702 ⭕\n\nBe aware that not all 6 digit number work,it depend on whether the entry is present in the website.\n\nadditional note:\n-the /random command might take a long time\n-you must be logged in to download Doujinshi\n-You might want to use VPN or change your browser DNS/device DNS to acces the nhentai website";
            break;

        case "/list":
            $replyMsg = "
                \n /code #code: Get doujinshi (nhentai) information
                \n /ping: Ping the bot
                \n /random: get random doujinshi
                \n /list: Get list of the bot command";
            break;

        default:
            $replyMsg = "I don't recognize that command. Type /list for available commands.";
            break;
    }

    return $replyMsg;
}

function parseDoujinInfo(string $doujinId, string $state = "predetermined"): string
{
    $url = "https://nhentai.net/g/$doujinId/";
    $download_url = "https://nhentai.net/g/$doujinId/download";

    try {
        $html = fetchHTML($url);
        // Check for 404 – Not Found
        if ($state === "randomized") {
            
            //Parse the HTML
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
        
            $xpath = new DOMXPath($dom);
        
            // Helper to fetch text content or return empty string
            $getNodeContent = function ($query) use ($xpath) {
                $node = $xpath->query($query)->item(0);
                return $node ? trim($node->textContent) : '';
            };

            //check whether there's container error
            $notFound = $getNodeContent("//div[@class='container error']");

            if (!empty($notFound)) {
                // Generate a new random code and retry
                return parseDoujinInfo(random(), "randomized");
            }
        } else {
            if (!$html) {
                return "Failed to fetch data. Either the input code is invalid or there's a connection problem.";
            }
        }

        // Extract content using a helper function
        $extractedData = extractDoujinContent($html);

        // Format the response, omitting empty fields
        $response = [];
        foreach ($extractedData as $key => $value) {
            if (!empty($value)) {
                $response[] = "<b>" . ucfirst($key) . ":</b> $value";
            }
        }

        // Append the page link
        $response[] = "\n<b><a href=\"$url\">PAGE LINK</a></b>\n<b><a href=\"$download_url\">DOWNLOAD LINK</a></b>";

        return implode("\n", $response);
    } catch (\Exception $e) {
        // Log exception (use a logger in a real application)
        file_put_contents(__DIR__ . '/log/error.log', $e->getMessage() . "\n", FILE_APPEND);
        return "An error occurred while fetching the data. Please try again later.";
    }
}

function extractDoujinContent(string $html): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html);

    $xpath = new DOMXPath($dom);

    // Helper to fetch text content or return empty string
    $getNodeContent = function ($query) use ($xpath) {
        $node = $xpath->query($query)->item(0);
        return $node ? trim($node->textContent) : '';
    };

    $getNodesContent = function ($query) use ($xpath) {
        $nodes = $xpath->query($query);
        $result = [];
        foreach ($nodes as $node) {
            $result[] = trim($node->textContent);
        }
        return $result;
    };

    // Extracting fields
    $title = $getNodeContent("//h1[@class='title']");
    $code = $getNodeContent("//h3[@id='gallery_id']/span[@class='hash']/following-sibling::text()");
    $tagsContent = $getNodeContent("//meta[@name='twitter:description']/@content");
    $artistString = implode(', ', $getNodesContent("//a[contains(@href, '/artist/')]/span[@class='name']"));
    $parodyString = implode(', ', $getNodesContent("//a[contains(@href, '/parody/')]/span[@class='name']"));
    $characterString = implode(', ', $getNodesContent("//a[contains(@href, '/character/')]/span[@class='name']"));
    $languageString = implode(', ', $getNodesContent("//a[contains(@href, '/language/')]/span[@class='name']"));
    $groupString = implode(', ', $getNodesContent("//a[contains(@href, '/group/')]/span[@class='name']"));
    $categoryString = implode(', ', $getNodesContent("//a[contains(@href, '/category/')]/span[@class='name']"));
    $pageString = implode(', ', $getNodesContent("//a[contains(@href, '/search/')]/span[@class='name']"));
    $uploadString = implode(', ', $getNodesContent("//time[@class='nobold']"));

    // Return all extracted fields in an associative array
    return [
        'Title' => $title,
        'Code' => $code,
        'Parodies' => $parodyString,
        'Characters' => $characterString,
        'Tags' => $tagsContent,
        'Artists' => $artistString,
        'Groups' => $groupString,
        'Languages' => $languageString,
        'Categories' => $categoryString,
        'Pages' => $pageString,
        'Uploaded' => $uploadString
    ];
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

function random()
{
    return substr(str_shuffle("1234567890"), 0, 6);
}
