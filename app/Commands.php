<?php

namespace App;

use DOMDocument;
use DOMXPath;

require __DIR__ . '/helper_function.php';

function handleCommand(string $userMessage, string $fullName): string
{
    $replyMsg = "";

    // Check for "/code #code" using regex
    if (preg_match('/^\/code\s+#(\d+)/i', $userMessage, $matches)) {
        $doujinId = $matches[1];
        return parseDoujinInfo($doujinId, 'predetermined');
    }

    // Check for "/tag #tag" using regex
    if (preg_match('/^\/tag\s+#([a-zA-Z0-9\-]+)/i', $userMessage, $matches)) {
        $tag = $matches[1];

        $tagValidation = isValidTag($tag);

        // Validate whether tag Exist on the website
        if ($tagValidation !== true) {
          return $tagValidation;
        }
        return GetDoujinByTag(randomSixDigit(), $tag);
    }

    // Check for "/tag #tag" using regex
    if (preg_match('/^\/lang\s+#([a-zA-Z0-9\-]+)/i', $userMessage, $matches)) {
        $lang = $matches[1];

        $langValidation = isValidLang($lang);

        // Validate whether lang Exist on the website
        if ($langValidation !== true) {
          return $langValidation;
        }
        return GetDoujinByLanguage(randomSixDigit(), $lang);
    }

    // Match basic commands
    switch ($userMessage) {
        case "/start":
            $replyMsg = "Konnichiwa, $fullName! 👋 Welcome to Doujinshi BOT! 
                \n /code #code: Get doujinshi (nhentai) information
                \n /tag #tag: Get Random Doujinshi by tag
                \n /lang #language:get random Doujinshi by Language
                \n /ping: Ping the bot
                \n /random: get random doujinshi
                \n /help: get help using the bot
                \n /list: Get list of the bot command";
            break;

        case "/ping":
            $replyMsg = "Pong! 🏓 The bot is functioning normally.";
            break;

        case "/random":
            $replyMsg = parseDoujinInfo(randomSixDigit(), "randomized");
            break;

        case "/help":
            $replyMsg = "1.format for code is #[6 digit num]
            \n\n/code ##539702 ❌
            \n/code 539702 ❌
            \n/code #539702 ⭕
            \n\nBe aware that not all 6 digit number work,it depend on whether the entry is present in the website.
            \n\n2.format for tag is #[text],all lowercase and word is separated by '-'
            \n\n/tag ##dark-skin ❌
            \n/tag #darkskin ❌
            \n/tag #DarkSkin ❌
            \n/tag #dark-skin ⭕
            \n\nBe aware that not all tag you inputted is available on the website.
            \n\nadditional note:
            \n-the /tag command might take a long time
            \n-you must be logged in to download Doujinshi
            \n-You might want to use VPN or change your browser DNS/device DNS to acces the nhentai website";
            break;

        case "/list":
            $replyMsg = "
                \n /code #code: Get doujinshi (nhentai) information
                \n /tag #tag: get Random Doujinshi by tag
                \n /lang #language:get random Doujinshi by Language
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

/**
 *Function to Parse the html of Doujinshi page of doujinId,then validate whether it exist or not using  getNotFound
 *use foreign function:fetchHTML,GetNotFound,randomSixDigit from helper_function
 * @param string $doujinId
 * @param string $state
 * @return string
 */
function parseDoujinInfo(string $doujinId, string $state = "predetermined"): string
{
    $url = "https://nhentai.net/g/$doujinId/";
    $download_url = $url . "download";

    try {
        $html = fetchHTML($url);
        // Check for 404 – Not Found
        if ($state === "randomized") {

            $notFound = getNotFound($html);

            if (!empty($notFound)) {
                // Generate a new random code and retry
                return parseDoujinInfo(randomSixDigit(), "randomized");
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


/**
 *Function to Parse the html of Doujinshi page of doujinId,then validate whether it exist or not using  getNotFound
 *use foreign function:fetchHTML,GetNotFound,randomSixDigit,hasRequestedTag from helper_function
 *HOW IT WORK:
 *PRE:tag is checked whether it's available on the website or not (refer to handleCommand function)
 *random six digit number is generated to populate doujinId,and then doujinshi page of that doujinId is parsed.
 *then it will check whether the doujinshi has the requested tag.if true then return the doujin information,if false then do the function again
 *until it doujinshi page has the requested tag or ratelimit is reached.
 * @param string $doujinId
 * @param string $tag
 * @param int $ratelimit
 * @return string
 */
function GetDoujinByTag(string $doujinId, string $tag, int $ratelimit = 0)
{
    // Define the rate limit
    $maxRateLimit = 30;

    $url = "https://nhentai.net/g/$doujinId/";
    $download_url = $url . "download";

    $html = fetchHTML($url);

    // Check for 404 – Not Found
    $notFound = getNotFound($html);

    // If ratelimit hasn't been reached yet
    if ($ratelimit < $maxRateLimit) {
        if (!empty($notFound)) {
            // Generate a new random code and retry
            return getDoujinByTag(randomSixDigit(), $tag, $ratelimit);
        } else {
            // Validate the tag
            $hasTag = hasRequestedTag($tag, $html);

            if ($hasTag === false) {
                // Increment the ratelimit on failed tag validation
                $ratelimit++;
                return getDoujinByTag(randomSixDigit(), $tag, $ratelimit);
            }
        }
    } else {
        // Return a message when the rate limit is reached
        return "Rate limit of $maxRateLimit hit reached. Please try again later and make sure that the tag you provide is formatted correctly,use /help for guide on correct format.\n\nInputting valid tag with small entry (under 500) may risk on timeout";
    }

    if (!$html) {
        return "Failed to fetch data. Either the input code is invalid or there's a connection problem.";
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
}
/**
 *Function to Parse the html of Doujinshi page of doujinId,then validate whether it exist or not using  getNotFound
 *use foreign function:fetchHTML,GetNotFound,randomSixDigit,hasRequestedTag from helper_function
 *HOW IT WORK:
 *PRE:tag is checked whether it's available on the website or not (refer to handleCommand function)
 *random six digit number is generated to populate doujinId,and then doujinshi page of that doujinId is parsed.
 *then it will check whether the doujinshi has the requested tag.if true then return the doujin information,if false then do the function again
 *until it doujinshi page has the requested tag or ratelimit is reached.
 * @param string $doujinId
 * @param string $tag
 * @param int $ratelimit
 * @return string
 */
function GetDoujinByLanguage(string $doujinId, string $lang, int $ratelimit = 0)
{
    // Define the rate limit
    $maxRateLimit = 30;

    $url = "https://nhentai.net/g/$doujinId/";
    $download_url = $url . "download";

    $html = fetchHTML($url);

    // Check for 404 – Not Found
    $notFound = getNotFound($html);

    // If ratelimit hasn't been reached yet
    if ($ratelimit < $maxRateLimit) {
        if (!empty($notFound)) {
            // Generate a new random code and retry
            return GetDoujinByLanguage(randomSixDigit(), $lang, $ratelimit);
        } else {
            // Validate the tag
            $hasTag = hasRequestedLang($lang, $html);

            if ($hasTag === false) {
                // Increment the ratelimit on failed tag validation
                $ratelimit++;
                return GetDoujinByLanguage(randomSixDigit(), $lang, $ratelimit);
            }
        }
    } else {
        // Return a message when the rate limit is reached
        return "Rate limit of $maxRateLimit hit reached. Please try again later and make sure that the tag you provide is formatted correctly,use /help for guide on correct format.\n\nInputting valid tag with small entry (under 500) may risk on timeout";
    }

    if (!$html) {
        return "Failed to fetch data. Either the input code is invalid or there's a connection problem.";
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
}

/**
 * function to extract doujin content such as code,title,tag,etc from the parsed html
 * @param string $html
 * @return array
 */
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
    $tagsContent = implode(', ', $getNodesContent("//a[contains(@href, '/tag/')]/span[@class='name']"));
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
