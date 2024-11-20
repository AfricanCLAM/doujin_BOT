<?php
function handleCommand($userMessage, $fullName)
{
    $replyMsg = "";

    // Regex to match "/code [code]"
    if (preg_match('/^\/code\s+#?(\d+)/i', $userMessage, $matches)) {
        // echo ('doujin Id:' . $matches[1]);
        $doujinId = $matches[1]; // Extract the code
        $replyMsg = parse($doujinId); // Call the parse function with the extracted code
        return $replyMsg;
    }

    switch ($userMessage) {
        case "/start":
            $replyMsg = "Hello, $fullName! 👋 welcome to SALMAN BOT! 
            \n /code #code: get doujinshi (nhentai) information
            \n /ping: ping the Bot
            \n /help: get help using the bot";
            break;
        case "/ping":
            $replyMsg = "Pong!🏓,the Bot is functioning normally";
            break;
        case "/help":
            $replyMsg = "
            \n /code #code: get doujinshi (nhentai) information
            \n /ping: ping the Bot
            \n /help: get help using the bot";
            break;
        default:
            $replyMsg = "I don't recognize that command. Type /help for available commands.";
            break;
    }

    return $replyMsg;
}

function parse($doujinId)
{
    $url = "https://nhentai.net/g/$doujinId/";
    // echo ('Doujin URL: ' . $url);

    // Fetch the HTML content using cURL
    function fetchHTML($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_DNS_SERVERS, "1.1.1.1");
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        // curl_setopt($ch, CURLOPT_RESOLVE, ["nhentai.net:443:1.1.1.1"]);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //     'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        //     'Accept-Encoding: gzip, deflate, br',
        //     'Accept-Language: en-US,en;q=0.5',
        //     'Cache-Control: no-cache',
        //     'Connection: keep-alive',
        // ]);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36");
        $html = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "cURL Error: " . curl_error($ch) . "\n";
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $html;
    }

    $html = fetchHTML($url);

    if ($html) {
        file_put_contents('debug_parse.html', $html);
        // Load the HTML into DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML($html); // Suppress warnings from malformed HTML

        // Use DOMXPath for querying the DOM
        $xpath = new DOMXPath($dom);

        // Extract the Title
        $titleNode = $xpath->query("//h1[@class='title']")->item(0);
        $title = "";
        if ($titleNode) {
            foreach ($titleNode->childNodes as $child) {
                $title .= $child->textContent;
            }
        }

        // Extract the Code
        $codeNode = $xpath->query("//h3[@id='gallery_id']/span[@class='hash']")->item(0);
        $code = $codeNode ? $codeNode->nextSibling->textContent : "";

        $tagsNode = $xpath->query("//meta[@name='twitter:description']")->item(0);
        $tagsContent = $tagsNode ? $tagsNode->getAttribute('content') : null;

        // $link = "<a href=\"$url\">LINK</a>";
        // Output the results
        return "<b>Title:</b> $title\n<b>tags:</b> $tagsContent \n<b>Code:</b> $code\n\n<b><a href=\"$url\">LINK</a></b>";
    }

    return "Failed to fetch data. Please ensure the code is valid.";
}
